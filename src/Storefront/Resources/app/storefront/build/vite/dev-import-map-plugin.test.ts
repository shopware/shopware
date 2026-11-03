// @vitest-environment node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { EventEmitter } from 'node:events';
import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest';
import type { ViteDevServer } from 'vite';
import { devImportMapPlugin } from './dev-import-map-plugin';

type MockServer = ViteDevServer & {
    middlewares: { use: ReturnType<typeof vi.fn> };
    watcher: EventEmitter & { add: ReturnType<typeof vi.fn> };
    ws: { send: ReturnType<typeof vi.fn> };
    httpServer: EventEmitter;
};

function resolveHook<T extends (...args: never[]) => unknown>(
    hook: T | { handler: T } | undefined,
): T | undefined {
    if (typeof hook === 'function') {
        return hook;
    }

    return hook?.handler;
}

function createMockServer(port: number, origin?: string): MockServer {
    const watcher = new EventEmitter() as EventEmitter & { add: ReturnType<typeof vi.fn> };
    watcher.add = vi.fn();

    const middlewares: Array<{ prefix?: string; handler: (...args: unknown[]) => unknown }> = [];
    const middlewareUse = vi.fn((first: string | ((...args: unknown[]) => unknown), second?: (...args: unknown[]) => unknown) => {
        if (typeof first === 'string' && second) {
            middlewares.push({ prefix: first, handler: second });
            return;
        }

        if (typeof first === 'function') {
            middlewares.push({ handler: first });
        }
    });

    const server = {
        config: {
            server: { port, origin },
            logger: {
                info: vi.fn(),
                warn: vi.fn(),
                error: vi.fn(),
            },
        },
        watcher,
        ws: { send: vi.fn() },
        middlewares: { use: middlewareUse },
        httpServer: new EventEmitter(),
    } as unknown as MockServer;

    Object.defineProperty(server, '__middlewares', {
        value: middlewares,
    });

    return server;
}

async function waitUntil(assertion: () => void, timeoutMs = 2000): Promise<void> {
    const start = Date.now();
     
    while (true) {
        try {
            assertion();
            return;
        } catch (error) {
            if (Date.now() - start > timeoutMs) {
                throw error;
            }

            await new Promise(resolve => setTimeout(resolve, 25));
        }
    }
}

describe('devImportMapPlugin', () => {
    let fixtureRoot: string;

    beforeAll(() => {
        fixtureRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'dev-import-map-plugin-'));
    });

    afterAll(() => {
        fs.rmSync(fixtureRoot, { recursive: true, force: true });
    });

    it('writes dev map with component imports and keeps running when theme json is malformed', async () => {
        const viteRoot = path.join(fixtureRoot, 'src/Storefront/Resources/app/storefront');
        const componentsRoot = path.join(fixtureRoot, 'src/Storefront/Resources/views/components');
        const varRoot = path.join(fixtureRoot, 'var');
        fs.mkdirSync(path.join(viteRoot, 'src'), { recursive: true });
        fs.mkdirSync(path.join(componentsRoot, 'Sw/Header'), { recursive: true });
        fs.mkdirSync(path.join(componentsRoot, 'Sw/Search/Action'), { recursive: true });
        fs.mkdirSync(varRoot, { recursive: true });

        fs.writeFileSync(path.join(viteRoot, 'src/shopware.ts'), 'export const test = true;');
        fs.writeFileSync(path.join(componentsRoot, 'Sw/Header/Navbar.ts'), 'export default class Navbar {}');
        fs.writeFileSync(path.join(componentsRoot, 'Sw/Search/Action/index.js'), 'export default class SearchAction {}');
        fs.writeFileSync(path.join(componentsRoot, 'Sw/Header/Navbar.css'), '.navbar { color: red; }');
        fs.writeFileSync(path.join(varRoot, 'plugins.json'), JSON.stringify({
            Storefront: {
                basePath: 'src/Storefront',
                technicalName: 'storefront',
            },
        }));
        fs.writeFileSync(path.join(varRoot, 'theme-files.json'), '{invalid');

        const plugin = devImportMapPlugin(fixtureRoot);
        const server = createMockServer(5180);
        (resolveHook(plugin.configResolved) as ((...args: unknown[]) => unknown) | undefined)?.call({}, { root: viteRoot } as never);
        (resolveHook(plugin.configureServer) as ((...args: unknown[]) => unknown) | undefined)?.call({}, server);
        server.httpServer.emit('listening');

        const flagFile = path.join(fixtureRoot, 'var/cache/storefront_components.dev.json');
        await waitUntil(() => {
            expect(fs.existsSync(flagFile)).toBe(true);
        });

        const devMap = JSON.parse(fs.readFileSync(flagFile, 'utf-8')) as {
            imports: Record<string, string>;
            styles: string[];
            themeId?: string;
        };

        expect(devMap.imports['shopware']).toBe('http://localhost:5180/src/shopware.ts');
        expect(devMap.imports['Sw:Header:Navbar']).toContain('/@fs');
        expect(devMap.imports['Sw:Search:Action']).toContain('/@fs');
        expect(devMap.imports['Sw:Search:Action:index']).toBeUndefined();
        expect(devMap.styles).toContain('http://localhost:5180/theme-scss/all.css');
        expect(devMap.styles).toContain('http://localhost:5180/__sw-comp-css/Sw/Header/Navbar.css');
        expect(devMap.themeId).toBeUndefined();

        server.httpServer.emit('close');
        expect(fs.existsSync(flagFile)).toBe(false);
    });

    it('serves component css files via middleware, rewrites map before add-triggered reload, and swaps changed css in hmr mode', async () => {
        const pluginRoot = path.join(fixtureRoot, 'second-project');
        const viteRoot = path.join(pluginRoot, 'src/Storefront/Resources/app/storefront');
        const namespacedCompRoot = path.join(pluginRoot, 'custom/plugins/TestPlugin/Resources/views/components/Foo');
        const varRoot = path.join(pluginRoot, 'var');
        fs.mkdirSync(path.join(viteRoot, 'src'), { recursive: true });
        fs.mkdirSync(namespacedCompRoot, { recursive: true });
        fs.mkdirSync(varRoot, { recursive: true });

        fs.writeFileSync(path.join(viteRoot, 'src/shopware.ts'), 'export const test = true;');
        fs.writeFileSync(path.join(namespacedCompRoot, 'Bar.css'), '.bar { color: blue; }');
        fs.writeFileSync(path.join(varRoot, 'plugins.json'), JSON.stringify({
            TestPlugin: {
                basePath: 'custom/plugins/TestPlugin',
                technicalName: 'test-plugin',
            },
        }));

        const plugin = devImportMapPlugin(pluginRoot, [], { devUpdate: 'hmr' });
        const server = createMockServer(5181);
        (resolveHook(plugin.configResolved) as ((...args: unknown[]) => unknown) | undefined)?.call({}, { root: viteRoot } as never);
        (resolveHook(plugin.configureServer) as ((...args: unknown[]) => unknown) | undefined)?.call({}, server);
        server.httpServer.emit('listening');

        const flagFile = path.join(pluginRoot, 'var/cache/storefront_components.dev.json');
        await waitUntil(() => {
            expect(fs.existsSync(flagFile)).toBe(true);
        });

        // No Storefront module uses import.meta.hot, so the Vite client must be loaded explicitly and first.
        // The Twig hot reload must run before main.js.
        const { scripts } = JSON.parse(fs.readFileSync(flagFile, 'utf-8')) as { scripts: string[] };
        expect(scripts.slice(0, 3)).toEqual([
            'http://localhost:5181/@vite/client',
            'http://localhost:5181/src/dev/twig-hmr/twig-hmr.vite.js',
            'http://localhost:5181/src/main.js',
        ]);

        const middlewareEntries = (server as unknown as { __middlewares: Array<{ prefix?: string; handler: (...args: unknown[]) => unknown }> }).__middlewares;
        const cssMiddleware = middlewareEntries.find(entry => entry.prefix === '/__sw-comp-css/');
        expect(cssMiddleware).toBeDefined();

        const res = {
            headers: {} as Record<string, string>,
            body: '',
            setHeader(key: string, value: string) {
                this.headers[key] = value;
            },
            end(value: string) {
                this.body = value;
            },
        };
        const next = vi.fn();

        cssMiddleware!.handler(
            { url: '/TestPlugin/Foo/Bar.css' },
            res,
            next,
        );

        expect(res.headers['Content-Type']).toBe('text/css; charset=utf-8');
        expect(res.body).toContain('color: blue');
        expect(next).not.toHaveBeenCalled();

        const newStylePath = path.join(namespacedCompRoot, 'Baz.css');
        fs.writeFileSync(newStylePath, '.baz { color: green; }');
        server.watcher.emit('add', newStylePath);

        await waitUntil(() => {
            expect(server.ws.send).toHaveBeenCalledWith({ type: 'full-reload' });
            const map = JSON.parse(fs.readFileSync(flagFile, 'utf-8')) as { styles: string[] };
            expect(map.styles).toContain('http://localhost:5181/__sw-comp-css/TestPlugin/Foo/Baz.css');
        });

        server.ws.send.mockClear();
        server.watcher.emit('change', path.join(namespacedCompRoot, 'Bar.css'));

        expect(server.ws.send).toHaveBeenCalledOnce();
        expect(server.ws.send).toHaveBeenCalledWith({
            type: 'update',
            updates: ['/__sw-comp-css/TestPlugin/Foo/Bar.css', '/__sw-comp-css/TestPlugin/Foo/Baz.css'].map(stylePath => ({
                type: 'css-update',
                path: stylePath,
                acceptedPath: stylePath,
                timestamp: expect.any(Number) as number,
            })),
        });

        const swappedRes = { ...res, headers: {} as Record<string, string>, body: '' };
        cssMiddleware!.handler(
            { url: '/TestPlugin/Foo/Bar.css?t=1793618997559' },
            swappedRes,
            next,
        );

        expect(swappedRes.body).toContain('color: blue');
        expect(next).not.toHaveBeenCalled();
    });

    it.each([
        { devUpdate: 'reload' as const, clientLoaded: true, sent: [{ type: 'full-reload' }] },
        { devUpdate: 'manual' as const, clientLoaded: false, sent: [] },
    ])('lists the Vite client and sends $sent.length message(s) on a style change in $devUpdate mode', async ({ devUpdate, clientLoaded, sent }) => {
        const pluginRoot = path.join(fixtureRoot, `${devUpdate}-project`);
        const viteRoot = path.join(pluginRoot, 'src/Storefront/Resources/app/storefront');
        const compRoot = path.join(pluginRoot, 'custom/plugins/TestPlugin/Resources/views/components/Foo');
        fs.mkdirSync(path.join(viteRoot, 'src'), { recursive: true });
        fs.mkdirSync(compRoot, { recursive: true });
        fs.mkdirSync(path.join(pluginRoot, 'var'), { recursive: true });

        fs.writeFileSync(path.join(viteRoot, 'src/shopware.ts'), 'export const test = true;');
        fs.writeFileSync(path.join(compRoot, 'Bar.css'), '.bar { color: blue; }');
        fs.writeFileSync(path.join(pluginRoot, 'var/plugins.json'), JSON.stringify({
            TestPlugin: {
                basePath: 'custom/plugins/TestPlugin',
                technicalName: 'test-plugin',
            },
        }));

        const plugin = devImportMapPlugin(pluginRoot, [], { devUpdate });
        const server = createMockServer(5182);
        (resolveHook(plugin.configResolved) as ((...args: unknown[]) => unknown) | undefined)?.call({}, { root: viteRoot } as never);
        (resolveHook(plugin.configureServer) as ((...args: unknown[]) => unknown) | undefined)?.call({}, server);
        server.httpServer.emit('listening');

        const flagFile = path.join(pluginRoot, 'var/cache/storefront_components.dev.json');
        await waitUntil(() => {
            expect(fs.existsSync(flagFile)).toBe(true);
        });

        const { scripts } = JSON.parse(fs.readFileSync(flagFile, 'utf-8')) as { scripts: string[] };
        expect(scripts.includes('http://localhost:5182/@vite/client')).toBe(clientLoaded);
        expect(scripts).toContain('http://localhost:5182/src/main.js');

        server.watcher.emit('change', path.join(compRoot, 'Bar.css'));
        expect(server.ws.send.mock.calls.map(call => call[0] as unknown)).toEqual(sent);

        server.ws.send.mockClear();
        const newStylePath = path.join(compRoot, 'Baz.css');
        fs.writeFileSync(newStylePath, '.baz { color: green; }');
        server.watcher.emit('add', newStylePath);

        await waitUntil(() => {
            const map = JSON.parse(fs.readFileSync(flagFile, 'utf-8')) as { styles: string[] };
            expect(map.styles).toContain('http://localhost:5182/__sw-comp-css/TestPlugin/Foo/Baz.css');
        });
        expect(server.ws.send.mock.calls.map(call => call[0] as unknown)).toEqual(sent);

        server.httpServer.emit('close');
    });

    it('rejects path traversal attempts in component css middleware', () => {
        const pluginRoot = path.join(fixtureRoot, 'third-project');
        const viteRoot = path.join(pluginRoot, 'src/Storefront/Resources/app/storefront');
        const namespacedCompRoot = path.join(pluginRoot, 'custom/plugins/TestPlugin/Resources/views/components/Foo');
        const varRoot = path.join(pluginRoot, 'var');
        fs.mkdirSync(path.join(viteRoot, 'src'), { recursive: true });
        fs.mkdirSync(namespacedCompRoot, { recursive: true });
        fs.mkdirSync(varRoot, { recursive: true });

        fs.writeFileSync(path.join(viteRoot, 'src/shopware.ts'), 'export const test = true;');
        fs.writeFileSync(path.join(varRoot, 'plugins.json'), JSON.stringify({
            TestPlugin: {
                basePath: 'custom/plugins/TestPlugin',
                technicalName: 'test-plugin',
            },
        }));
        fs.writeFileSync(path.join(pluginRoot, 'outside.css'), '.outside { color: red; }');

        const plugin = devImportMapPlugin(pluginRoot);
        const server = createMockServer(5182);
        (resolveHook(plugin.configResolved) as ((...args: unknown[]) => unknown) | undefined)?.call({}, { root: viteRoot } as never);
        (resolveHook(plugin.configureServer) as ((...args: unknown[]) => unknown) | undefined)?.call({}, server);
        server.httpServer.emit('listening');

        const middlewareEntries = (server as unknown as { __middlewares: Array<{ prefix?: string; handler: (...args: unknown[]) => unknown }> }).__middlewares;
        const cssMiddleware = middlewareEntries.find(entry => entry.prefix === '/__sw-comp-css/');
        expect(cssMiddleware).toBeDefined();

        const res = {
            headers: {} as Record<string, string>,
            body: '',
            setHeader(key: string, value: string) {
                this.headers[key] = value;
            },
            end(value: string) {
                this.body = value;
            },
        };
        const next = vi.fn();

        cssMiddleware!.handler(
            { url: '/TestPlugin/../../../../../../outside.css' },
            res,
            next,
        );

        expect(next).toHaveBeenCalledOnce();
        expect(res.headers['Content-Type']).toBeUndefined();
        expect(res.body).toBe('');
    });

    it('prefers configured Vite origin for dev map URLs', async () => {
        const pluginRoot = path.join(fixtureRoot, 'fourth-project');
        const viteRoot = path.join(pluginRoot, 'src/Storefront/Resources/app/storefront');
        const componentsRoot = path.join(pluginRoot, 'src/Storefront/Resources/views/components');
        const varRoot = path.join(pluginRoot, 'var');
        fs.mkdirSync(path.join(viteRoot, 'src'), { recursive: true });
        fs.mkdirSync(path.join(componentsRoot, 'Sw/Header'), { recursive: true });
        fs.mkdirSync(varRoot, { recursive: true });

        fs.writeFileSync(path.join(viteRoot, 'src/shopware.ts'), 'export const test = true;');
        fs.writeFileSync(path.join(componentsRoot, 'Sw/Header/Navbar.css'), '.navbar { color: red; }');
        fs.writeFileSync(path.join(varRoot, 'plugins.json'), JSON.stringify({
            Storefront: {
                basePath: 'src/Storefront',
                technicalName: 'storefront',
            },
        }));
        fs.writeFileSync(path.join(varRoot, 'theme-files.json'), JSON.stringify({ themeId: 'test-theme-id' }));

        const plugin = devImportMapPlugin(pluginRoot);
        const server = createMockServer(5183, 'http://host.docker.internal:5183/');
        (resolveHook(plugin.configResolved) as ((...args: unknown[]) => unknown) | undefined)?.call({}, { root: viteRoot } as never);
        (resolveHook(plugin.configureServer) as ((...args: unknown[]) => unknown) | undefined)?.call({}, server);
        server.httpServer.emit('listening');

        const flagFile = path.join(pluginRoot, 'var/cache/storefront_components.dev.json');
        await waitUntil(() => {
            expect(fs.existsSync(flagFile)).toBe(true);
        });

        const devMap = JSON.parse(fs.readFileSync(flagFile, 'utf-8')) as {
            imports: Record<string, string>;
            styles: string[];
        };

        expect(devMap.imports['shopware']).toBe('http://host.docker.internal:5183/src/shopware.ts');
        expect(devMap.styles).toContain('http://host.docker.internal:5183/theme-scss/all.css');
        expect(devMap.styles).toContain('http://host.docker.internal:5183/__sw-comp-css/Sw/Header/Navbar.css');
    });
});
