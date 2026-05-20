// @vitest-environment node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { EventEmitter } from 'node:events';
import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest';
import type { ViteDevServer } from 'vite';
import { themeScssWatcherPlugin } from './theme-scss-watcher-plugin';

type MiddlewareHandler = (req: { url?: string }, res: { setHeader: (name: string, value: string) => void; end: (body: string) => void; statusCode?: number }, next: () => void) => void;

function createMockServer(): ViteDevServer & {
    watcher: EventEmitter & { add: ReturnType<typeof vi.fn> };
    ws: { send: ReturnType<typeof vi.fn> };
    middlewares: { use: ReturnType<typeof vi.fn> };
    httpServer: EventEmitter;
    __middlewares: MiddlewareHandler[];
    } {
    const watcher = new EventEmitter() as EventEmitter & { add: ReturnType<typeof vi.fn> };
    watcher.add = vi.fn();

    const handlers: MiddlewareHandler[] = [];
    const middlewares = {
        use: vi.fn((handler: MiddlewareHandler) => {
            handlers.push(handler);
        }),
    };

    return {
        config: {
            logger: {
                info: vi.fn(),
                warn: vi.fn(),
                error: vi.fn(),
            },
        },
        watcher,
        ws: { send: vi.fn() },
        middlewares,
        httpServer: new EventEmitter(),
        __middlewares: handlers,
    } as unknown as ViteDevServer & {
        watcher: EventEmitter & { add: ReturnType<typeof vi.fn> };
        ws: { send: ReturnType<typeof vi.fn> };
        middlewares: { use: ReturnType<typeof vi.fn> };
        httpServer: EventEmitter;
        __middlewares: MiddlewareHandler[];
    };
}

function resolveHook<T extends (...args: never[]) => unknown>(
    hook: T | { handler: T } | undefined,
): T | undefined {
    if (typeof hook === 'function') {
        return hook;
    }

    return hook?.handler;
}

describe('themeScssWatcherPlugin', () => {
    let fixtureRoot: string;

    beforeAll(() => {
        fixtureRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'theme-scss-watcher-plugin-'));
    });

    afterAll(() => {
        fs.rmSync(fixtureRoot, { recursive: true, force: true });
    });

    it('serves compiled css and reloads when theme-files.json changes', () => {
        const projectRoot = path.join(fixtureRoot, 'project');
        const varRoot = path.join(projectRoot, 'var');
        const styleA = path.join(projectRoot, 'theme-a.scss');
        const styleB = path.join(projectRoot, 'theme-b.scss');

        fs.mkdirSync(varRoot, { recursive: true });
        fs.writeFileSync(styleA, '$color: red; .a { color: $color; }');
        fs.writeFileSync(styleB, '$color: blue; .b { color: $color; }');

        const themeFilesPath = path.join(varRoot, 'theme-files.json');
        fs.writeFileSync(themeFilesPath, JSON.stringify({
            script: [],
            style: [{ filepath: styleA, extensions: [], resolveMapping: [], assetName: null }],
            themeId: 'theme-a',
            technicalName: 'Storefront',
            domainUrl: 'https://example.test',
        }));

        const plugin = themeScssWatcherPlugin(projectRoot);
        const server = createMockServer();
        (resolveHook(plugin.configureServer) as ((...args: unknown[]) => unknown) | undefined)?.call({}, server);

        const middleware = server.__middlewares[0];
        expect(middleware).toBeDefined();

        const response = {
            statusCode: 200,
            headers: {} as Record<string, string>,
            body: '',
            setHeader(name: string, value: string) {
                this.headers[name] = value;
            },
            end(body: string) {
                this.body = body;
            },
        };

        middleware?.(
            { url: '/theme-scss/all.css' },
            response,
            () => undefined,
        );

        expect(response.headers['Content-Type']).toContain('text/css');
        expect(response.body).toContain('.a');
        expect(server.watcher.add).toHaveBeenCalledWith(themeFilesPath);

        fs.writeFileSync(themeFilesPath, JSON.stringify({
            script: [],
            style: [{ filepath: styleB, extensions: [], resolveMapping: [], assetName: null }],
            themeId: 'theme-b',
            technicalName: 'Storefront',
            domainUrl: 'https://example.test',
        }));

        server.watcher.emit('change', themeFilesPath);

        expect(server.watcher.add).toHaveBeenCalledWith(styleB);
        expect(server.ws.send).toHaveBeenCalledWith({ type: 'full-reload' });
    });

    it('warns and exits cleanly when theme-files.json is missing or malformed', () => {
        const projectRoot = path.join(fixtureRoot, 'invalid-project');
        const varRoot = path.join(projectRoot, 'var');
        fs.mkdirSync(varRoot, { recursive: true });
        fs.writeFileSync(path.join(varRoot, 'theme-files.json'), '{invalid');

        const plugin = themeScssWatcherPlugin(projectRoot);
        const server = createMockServer();
        (resolveHook(plugin.configureServer) as ((...args: unknown[]) => unknown) | undefined)?.call({}, server);

        expect(server.config.logger.warn).toHaveBeenCalledOnce();
    });
});
