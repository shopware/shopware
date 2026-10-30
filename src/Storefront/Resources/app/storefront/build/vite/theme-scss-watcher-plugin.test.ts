// @vitest-environment node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { EventEmitter } from 'node:events';
import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest';
import * as SassEmbedded from 'sass-embedded';
import type { ViteDevServer } from 'vite';
import { themeScssWatcherPlugin } from './theme-scss-watcher-plugin';

// The sync Sass API corrupts its protobuf stream on Node >= 24.22 (sync-child-process
// ignores the byte offset of pooled stdout chunks), so the plugin must never use it.
vi.mock('sass-embedded', async (importOriginal) => {
    const original = await importOriginal<typeof SassEmbedded>();

    return {
        ...original,
        compileString: vi.fn(() => {
            throw new Error('The sync Sass API must not be used by the theme SCSS watcher.');
        }),
        compileStringAsync: vi.fn(original.compileStringAsync),
    };
});

type MockResponse = {
    statusCode: number;
    headers: Record<string, string>;
    body: string;
    ended: Promise<void>;
    setHeader: (name: string, value: string) => void;
    end: (body: string) => void;
};

type MiddlewareHandler = (req: { url?: string }, res: MockResponse, next: () => void) => void;

type MockServer = ViteDevServer & {
    config: { logger: { info: ReturnType<typeof vi.fn>; warn: ReturnType<typeof vi.fn>; error: ReturnType<typeof vi.fn> } };
    watcher: EventEmitter & { add: ReturnType<typeof vi.fn> };
    ws: { send: ReturnType<typeof vi.fn> };
    httpServer: EventEmitter;
    __middlewares: MiddlewareHandler[];
};

type StyleEntry = {
    filepath: string;
    extensions: string[];
    resolveMapping: Record<string, string> | string[];
    assetName: string | null;
};

function createMockServer(): MockServer {
    const watcher = new EventEmitter() as EventEmitter & { add: ReturnType<typeof vi.fn> };
    watcher.add = vi.fn();

    const handlers: MiddlewareHandler[] = [];

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
        middlewares: {
            use: vi.fn((handler: MiddlewareHandler) => {
                handlers.push(handler);
            }),
        },
        httpServer: new EventEmitter(),
        __middlewares: handlers,
    } as unknown as MockServer;
}

function createResponse(): MockResponse {
    let resolveEnd: () => void = () => undefined;
    const ended = new Promise<void>((resolve) => {
        resolveEnd = resolve;
    });

    return {
        statusCode: 200,
        headers: {},
        body: '',
        ended,
        setHeader(name: string, value: string) {
            this.headers[name] = value;
        },
        end(body: string) {
            this.body = body;
            resolveEnd();
        },
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

function startPlugin(projectRoot: string): MockServer {
    const plugin = themeScssWatcherPlugin(projectRoot);
    const server = createMockServer();
    (resolveHook(plugin.configureServer) as ((...args: unknown[]) => unknown) | undefined)?.call({}, server);

    return server;
}

async function requestCss(server: MockServer, url = '/theme-scss/all.css'): Promise<MockResponse> {
    const response = createResponse();
    server.__middlewares[0]?.({ url }, response, () => undefined);
    await response.ended;

    return response;
}

function styleEntry(filepath: string, resolveMapping: StyleEntry['resolveMapping'] = []): StyleEntry {
    return { filepath, extensions: [], resolveMapping, assetName: null };
}

function writeThemeFiles(projectRoot: string, themeId: string, style: StyleEntry[]): string {
    const themeFilesPath = path.join(projectRoot, 'var/theme-files.json');
    fs.mkdirSync(path.dirname(themeFilesPath), { recursive: true });
    fs.writeFileSync(themeFilesPath, JSON.stringify({
        script: [],
        style,
        themeId,
        technicalName: 'Storefront',
        domainUrl: 'https://example.test',
    }));

    return themeFilesPath;
}

function writeFile(filepath: string, content: string): string {
    fs.mkdirSync(path.dirname(filepath), { recursive: true });
    fs.writeFileSync(filepath, content);

    return filepath;
}

describe('themeScssWatcherPlugin', () => {
    let fixtureRoot: string;

    beforeAll(() => {
        fixtureRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'theme-scss-watcher-plugin-'));
    });

    afterAll(() => {
        fs.rmSync(fixtureRoot, { recursive: true, force: true });
    });

    it('serves compiled css and reloads when theme-files.json changes', async () => {
        const projectRoot = path.join(fixtureRoot, 'project');
        const styleA = writeFile(path.join(projectRoot, 'theme-a.scss'), '$color: red; .a { color: $color; }');
        const styleB = writeFile(path.join(projectRoot, 'theme-b.scss'), '$color: blue; .b { color: $color; }');
        const themeFilesPath = writeThemeFiles(projectRoot, 'theme-a', [styleEntry(styleA)]);

        const server = startPlugin(projectRoot);
        const response = await requestCss(server);

        expect(response.statusCode).toBe(200);
        expect(response.headers['Content-Type']).toContain('text/css');
        expect(response.headers['Cache-Control']).toBe('no-cache');
        expect(response.body).toContain('.a');
        expect(server.watcher.add).toHaveBeenCalledWith(themeFilesPath);

        writeThemeFiles(projectRoot, 'theme-b', [styleEntry(styleB)]);
        server.watcher.emit('change', themeFilesPath);

        expect(server.watcher.add).toHaveBeenCalledWith(styleB);
        await vi.waitFor(() => {
            expect(server.ws.send).toHaveBeenCalledWith({ type: 'full-reload' });
        });

        expect((await requestCss(server)).body).toContain('.b');
    });

    it('passes requests for other urls to the next middleware', () => {
        const projectRoot = path.join(fixtureRoot, 'other-url-project');
        writeThemeFiles(projectRoot, 'theme', [styleEntry(writeFile(path.join(projectRoot, 'a.scss'), '.a { color: red; }'))]);

        const server = startPlugin(projectRoot);
        const response = createResponse();
        const next = vi.fn();
        server.__middlewares[0]?.({ url: '/theme-scss/other.css' }, response, next);

        expect(next).toHaveBeenCalledOnce();
        expect(response.body).toBe('');
    });

    it('serves a placeholder comment when theme-files.json has no style entries', async () => {
        const projectRoot = path.join(fixtureRoot, 'empty-project');
        writeThemeFiles(projectRoot, 'theme', []);

        const response = await requestCss(startPlugin(projectRoot), '/theme-scss/all.css?t=1');

        expect(response.statusCode).toBe(200);
        expect(response.headers['Content-Type']).toContain('text/css');
        expect(response.body).toContain('No SCSS entries found');
    });

    it('responds with a 500 and logs the error when the SCSS does not compile', async () => {
        const projectRoot = path.join(fixtureRoot, 'broken-project');
        const brokenStyle = writeFile(path.join(projectRoot, 'broken.scss'), '.a { color: $undefined-variable; }');
        writeThemeFiles(projectRoot, 'broken', [styleEntry(brokenStyle)]);

        const server = startPlugin(projectRoot);
        const response = await requestCss(server);

        expect(response.statusCode).toBe(500);
        expect(response.body).toContain('Undefined variable');
        expect(server.config.logger.error).toHaveBeenCalledOnce();
    });

    it('reports compile failures that are not Error instances', async () => {
        const projectRoot = path.join(fixtureRoot, 'non-error-project');
        writeThemeFiles(projectRoot, 'theme', [styleEntry(writeFile(path.join(projectRoot, 'a.scss'), '.a { color: red; }'))]);
        vi.mocked(SassEmbedded.compileStringAsync).mockRejectedValueOnce('compiler crashed');

        const server = startPlugin(projectRoot);
        const response = await requestCss(server);

        expect(response.statusCode).toBe(500);
        expect(response.body).toContain('compiler crashed');
        expect(server.config.logger.error).toHaveBeenCalledWith(
            '[sw-theme-scss] Compile error: compiler crashed',
            { timestamp: true },
        );
    });

    it('resolves tilde aliases from resolveMapping and fails for imports nothing can resolve', async () => {
        const projectRoot = path.join(fixtureRoot, 'alias-project');
        const vendorRoot = path.join(projectRoot, 'vendor-lib');
        writeFile(path.join(vendorRoot, '_colors.scss'), '$brand: green;');
        const entry = writeFile(path.join(projectRoot, 'entry.scss'), '@import \'~lib/colors\'; .brand { color: $brand; }');
        writeThemeFiles(projectRoot, 'theme', [styleEntry(entry, { lib: vendorRoot })]);

        const server = startPlugin(projectRoot);
        const response = await requestCss(server);

        expect(response.statusCode).toBe(200);
        expect(response.body).toContain('color: green');

        writeFile(entry, '@import \'does-not-exist\';');
        const failed = await requestCss(server);

        expect(failed.statusCode).toBe(500);
        expect(failed.body).toContain('Can\'t find stylesheet to import');
    });

    it('prefers the per-theme variables file and watches every transitive import', async () => {
        const projectRoot = path.join(fixtureRoot, 'variables-project');
        const variablesFile = writeFile(path.join(projectRoot, 'var/theme-variables/theme-x.scss'), '$sw-color-brand-primary: purple;');
        writeFile(path.join(projectRoot, 'var/theme-variables.scss'), '$sw-color-brand-primary: black;');
        const partial = writeFile(path.join(projectRoot, '_partial.scss'), '.partial { color: $sw-color-brand-primary; }');
        const entry = writeFile(path.join(projectRoot, 'entry.scss'), '@import \'partial\';');
        writeThemeFiles(projectRoot, 'theme-x', [styleEntry(entry)]);

        const server = startPlugin(projectRoot);

        expect(server.watcher.add).toHaveBeenCalledWith(variablesFile);

        const response = await requestCss(server);

        expect(response.body).toContain('color: purple');
        expect(server.watcher.add).toHaveBeenCalledWith(partial);
    });

    it('falls back to the generic theme variables file', async () => {
        const projectRoot = path.join(fixtureRoot, 'fallback-variables-project');
        const fallbackFile = writeFile(path.join(projectRoot, 'var/theme-variables.scss'), '$sw-color-brand-primary: teal;');
        const entry = writeFile(path.join(projectRoot, 'entry.scss'), '.a { color: $sw-color-brand-primary; }');
        writeThemeFiles(projectRoot, 'theme-without-own-file', [styleEntry(entry)]);

        const server = startPlugin(projectRoot);

        expect(server.watcher.add).toHaveBeenCalledWith(fallbackFile);
        expect((await requestCss(server)).body).toContain('color: teal');
    });

    it('warms up the watch list once the server listens', async () => {
        const projectRoot = path.join(fixtureRoot, 'warm-up-project');
        const partial = writeFile(path.join(projectRoot, '_partial.scss'), '.partial { color: red; }');
        const entry = writeFile(path.join(projectRoot, 'entry.scss'), '@import \'partial\';');
        writeThemeFiles(projectRoot, 'theme', [styleEntry(entry)]);

        const server = startPlugin(projectRoot);
        server.httpServer.emit('listening');

        expect(server.config.logger.info).toHaveBeenCalledWith(
            '[sw-theme-scss] Serving 1 SCSS entry(s) as /theme-scss/all.css',
            { timestamp: true },
        );
        expect(server.config.logger.warn).toHaveBeenCalledWith(
            expect.stringContaining('No theme-variables file found for theme theme'),
            { timestamp: true },
        );

        await vi.waitFor(() => {
            expect(server.config.logger.info).toHaveBeenCalledWith(
                '[sw-theme-scss] Watching 2 SCSS file(s) (including transitive imports).',
                { timestamp: true },
            );
        });
        expect(server.watcher.add).toHaveBeenCalledWith(partial);
    });

    it('swallows warm-up and reload compile errors and leaves reporting to the middleware', async () => {
        const projectRoot = path.join(fixtureRoot, 'warm-up-broken-project');
        const entry = writeFile(path.join(projectRoot, 'entry.scss'), '.a { color: $undefined-variable; }');
        const themeFilesPath = writeThemeFiles(projectRoot, 'theme', [styleEntry(entry)]);
        writeFile(path.join(projectRoot, 'var/theme-variables/theme.scss'), '$unused: 1;');

        const server = startPlugin(projectRoot);
        server.httpServer.emit('listening');
        server.watcher.emit('change', themeFilesPath);

        await vi.waitFor(() => {
            expect(server.ws.send).toHaveBeenCalledWith({ type: 'full-reload' });
        });
        expect(server.config.logger.warn).not.toHaveBeenCalled();
        expect(server.config.logger.error).not.toHaveBeenCalled();
        expect(server.config.logger.info).not.toHaveBeenCalledWith(
            expect.stringContaining('Watching'),
            expect.anything(),
        );
    });

    it('reloads the page when a watched SCSS file changes and ignores other files', () => {
        const projectRoot = path.join(fixtureRoot, 'change-project');
        const entry = writeFile(path.join(projectRoot, 'entry.scss'), '.a { color: red; }');
        writeThemeFiles(projectRoot, 'theme', [styleEntry(entry)]);

        const server = startPlugin(projectRoot);

        server.watcher.emit('change', path.join(projectRoot, 'unrelated.scss'));
        expect(server.ws.send).not.toHaveBeenCalled();

        server.watcher.emit('change', entry);
        expect(server.ws.send).toHaveBeenCalledWith({ type: 'full-reload' });
        expect(server.config.logger.info).toHaveBeenCalledWith(
            expect.stringContaining('SCSS changed: entry.scss'),
            { timestamp: true },
        );
    });

    it('keeps the current entries when theme-files.json becomes malformed', async () => {
        const projectRoot = path.join(fixtureRoot, 'malformed-reload-project');
        const entry = writeFile(path.join(projectRoot, 'entry.scss'), '.kept { color: red; }');
        const themeFilesPath = writeThemeFiles(projectRoot, 'theme', [styleEntry(entry)]);

        const server = startPlugin(projectRoot);
        fs.writeFileSync(themeFilesPath, '{invalid');
        server.watcher.emit('change', themeFilesPath);

        expect(server.ws.send).not.toHaveBeenCalled();
        expect((await requestCss(server)).body).toContain('.kept');
    });

    it('warns and exits cleanly when theme-files.json is malformed', () => {
        const projectRoot = path.join(fixtureRoot, 'invalid-project');
        writeFile(path.join(projectRoot, 'var/theme-files.json'), '{invalid');

        const server = startPlugin(projectRoot);

        expect(server.config.logger.warn).toHaveBeenCalledOnce();
        expect(server.watcher.add).not.toHaveBeenCalled();
    });

    it('warns and exits cleanly when theme-files.json is missing', () => {
        const projectRoot = path.join(fixtureRoot, 'missing-project');
        fs.mkdirSync(projectRoot, { recursive: true });

        const server = startPlugin(projectRoot);

        expect(server.config.logger.warn).toHaveBeenCalledWith(
            expect.stringContaining('var/theme-files.json not found'),
            { timestamp: true },
        );
        expect(server.watcher.add).not.toHaveBeenCalled();
    });
});
