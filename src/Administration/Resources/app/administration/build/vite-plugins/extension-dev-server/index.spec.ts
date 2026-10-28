/**
 * @sw-package framework
 */
import fs from 'fs';
import { createServer } from 'http';
import type { IncomingMessage, ServerResponse } from 'http';
import type { Plugin, ResolvedConfig, UserConfig, ViteDevServer } from 'vite';
import ExtensionDevServerPlugin from './index';
import { getExtensionDevConfig } from '../../plugins.vite';
import type { ExtensionDefinition } from '../utils';
import type { ViteToolchain } from '../vite-toolchain';

jest.mock('../../plugins.vite', () => ({ getExtensionDevConfig: jest.fn(() => ({})) }));

type ConfigureHook = (server: ViteDevServer) => Promise<void>;
type Middleware = (request: IncomingMessage, response: ServerResponse, next: () => void) => void;

const extension: ExtensionDefinition = {
    name: 'Example',
    technicalName: 'example',
    technicalFolderName: 'example',
    basePath: '/plugins/Example/src',
    path: '/plugins/Example/src/Resources/app/administration/src',
    filePath: '/plugins/Example/src/Resources/app/administration/src/main.ts',
    isPlugin: true,
    isApp: false,
};

describe('shared extension listener', () => {
    let middleware: Middleware;
    let parent: ViteDevServer;
    let toolchain: ViteToolchain;
    let resolvedConfigs: ResolvedConfig[];
    let children: Array<{ close: jest.Mock; middlewares: jest.Mock }>;

    beforeEach(() => {
        resolvedConfigs = [];
        children = [];
        jest.spyOn(fs, 'writeFileSync').mockImplementation(() => {});
        jest.spyOn(console, 'error').mockImplementation(() => {});
        parent = {
            httpServer: createServer(),
            config: {
                server: {
                    host: 'admin.localhost',
                    port: 5190,
                    allowedHosts: ['admin.localhost'],
                    ws: { clientPort: 443, protocol: 'wss' },
                },
            },
            middlewares: {
                use: jest.fn((handler: Middleware) => {
                    middleware = handler;
                }),
            },
        } as unknown as ViteDevServer;

        toolchain = {
            vite: {
                createServer: jest.fn((config: UserConfig) => {
                    const resolved = { server: { port: 9999, ws: { port: 24678 } } } as unknown as ResolvedConfig;
                    const plugins = config.plugins as Plugin[];
                    const hook = plugins.at(-1)?.configResolved as (config: ResolvedConfig) => void;
                    hook(resolved);
                    resolvedConfigs.push(resolved);

                    const child = { close: jest.fn().mockResolvedValue(undefined), middlewares: jest.fn() };
                    children.push(child);
                    return Promise.resolve(child);
                }),
            },
        } as unknown as ViteToolchain;
    });

    afterEach(() => {
        parent.httpServer?.close();
        jest.restoreAllMocks();
    });

    async function start(extensions = [extension]) {
        const plugin = ExtensionDevServerPlugin(toolchain, extensions, false);
        await (plugin.configureServer as ConfigureHook)(parent);
        return plugin;
    }

    function request(url: string) {
        const response = { writeHead: jest.fn(), end: jest.fn() };
        const next = jest.fn();
        middleware({ url } as IncomingMessage, response as unknown as ServerResponse, next);
        return { response, next };
    }

    it('serves independent extension middleware through the parent HTTP and WebSocket listener', async () => {
        await start([extension, { ...extension, technicalName: 'other' }]);

        expect(getExtensionDevConfig).toHaveBeenCalledWith(toolchain, extension, false);
        resolvedConfigs.forEach((config) => {
            expect(config.server).toMatchObject({
                middlewareMode: { server: parent.httpServer },
                ws: { server: parent.httpServer, clientPort: 443, protocol: 'wss', port: undefined, path: undefined },
                allowedHosts: ['admin.localhost'],
                host: 'admin.localhost',
                port: 5190,
            });
        });

        request('/_internal_ext/example/main.ts?t=1');
        expect(children[0].middlewares).toHaveBeenCalledTimes(1);
        expect(children[1].middlewares).not.toHaveBeenCalled();
        request('/_internal_ext/other/main.ts');
        expect(children[1].middlewares).toHaveBeenCalledTimes(1);
        expect(request('/src/index.ts').next).toHaveBeenCalledTimes(1);
    });

    it('routes a Meteor app before the Admin plugin from the same bundle', async () => {
        await start([extension, { ...extension, isApp: true, isPlugin: false }]);

        request('/_internal_ext/example/meteor-app/index.html');

        expect(children[1].middlewares).toHaveBeenCalledTimes(1);
        expect(children[0].middlewares).not.toHaveBeenCalled();
        expect(fs.writeFileSync).toHaveBeenCalledWith(
            expect.stringMatching(/Resources\/sw-plugin-dev\.json$/),
            expect.stringContaining('/_internal_ext/example/meteor-app/index.html'),
        );
    });

    it('keeps healthy extensions available when another extension fails to start', async () => {
        jest.mocked(toolchain.vite.createServer).mockRejectedValueOnce(new Error('Invalid extension config'));
        await start([extension, { ...extension, technicalName: 'healthy' }]);

        const failed = request('/_internal_ext/example/main.ts');
        expect(failed.response.writeHead).toHaveBeenCalledWith(503, expect.anything());
        expect(failed.next).not.toHaveBeenCalled();
        request('/_internal_ext/healthy/main.ts');
        expect(children[0].middlewares).toHaveBeenCalledTimes(1);
    });

    it('closes all extension watchers with the parent and permits a fresh start', async () => {
        const plugin = await start();
        await (plugin.closeBundle as () => Promise<void>)();
        expect(children[0].close).toHaveBeenCalledTimes(1);

        await (plugin.configureServer as ConfigureHook)(parent);
        request('/_internal_ext/example/main.ts');
        expect(children[0].middlewares).not.toHaveBeenCalled();
        expect(children[1].middlewares).toHaveBeenCalledTimes(1);
        await (plugin.closeBundle as () => Promise<void>)();
    });

    it('honors a disabled parent WebSocket transport', async () => {
        Object.assign(parent.config.server, { ws: false, hmr: false });
        await start();
        expect(resolvedConfigs[0].server).toMatchObject({ ws: false, hmr: false });
    });
});
