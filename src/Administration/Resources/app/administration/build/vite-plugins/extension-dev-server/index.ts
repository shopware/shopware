/**
 * @sw-package framework
 */
import fs from 'fs';
import path from 'path';
import type { IncomingMessage, ServerResponse } from 'http';
import type { HmrOptions, Plugin, ViteDevServer } from 'vite';
import { getExtensionDevConfig } from '../../plugins.vite';
import { createPluginDevJson, getExtensionBasePath, runForEachExtension } from '../utils';
import type { ExtensionDefinition } from '../utils';
import type { ViteToolchain } from '../vite-toolchain';

type ServerGroup = {
    servers: Map<string, ViteDevServer>;
    restoreListenerLimit?: () => void;
    closing?: Promise<void>;
};

/** @private */
export default function ExtensionDevServerPlugin(
    toolchain: ViteToolchain,
    extensions: ExtensionDefinition[],
    useBundledDev: boolean,
): Plugin {
    const groups = new WeakMap<object, ServerGroup>();

    return {
        name: 'shopware-vite-plugin-extension-dev-server',
        apply: 'serve',

        async configureServer(parent) {
            // Vite constructs the next server before closing the previous one during a restart.
            const group: ServerGroup = { servers: new Map() };
            const { servers } = group;
            Object.values(parent.environments).forEach((environment) => groups.set(environment, group));
            const httpServer = parent.httpServer;
            if (!httpServer) {
                throw new Error('The Administration extension watcher requires an HTTP server.');
            }

            // Each extension adds an upgrade handler for its own WebSocket path to this HTTP server.
            const listenerLimit = httpServer.getMaxListeners();
            if (listenerLimit > 0) {
                httpServer.setMaxListeners(listenerLimit + extensions.length);
                group.restoreListenerLimit = () => httpServer.setMaxListeners(listenerLimit);
            }

            // Middleware servers must share the parent's transport, otherwise Vite opens a separate WebSocket port.
            const parentTransport = (parent.config.server as { ws?: HmrOptions | false }).ws;
            const transport: HmrOptions | false =
                parentTransport === false
                    ? false
                    : { ...parentTransport, server: httpServer, port: undefined, path: undefined };

            fs.writeFileSync(
                path.resolve(parent.config.root, '../../sw-plugin-dev.json'),
                JSON.stringify(createPluginDevJson(extensions, useBundledDev, true)),
            );

            await runForEachExtension(extensions, 'start', async (extension) => {
                const base = getExtensionBasePath(extension, true);
                const config = getExtensionDevConfig(toolchain, extension, useBundledDev);
                let startingServer: ViteDevServer | undefined;

                let server: ViteDevServer;
                try {
                    server = await toolchain.vite.createServer({
                        ...config,
                        base,
                        plugins: [
                            ...(config.plugins ?? []),
                            {
                                name: 'shopware-vite-plugin-extension-transport',
                                enforce: 'post',
                                configResolved(resolved) {
                                    // Apply after the extension's config so it cannot accidentally create another listener.
                                    Object.assign(resolved.server, {
                                        middlewareMode: { server: httpServer },
                                        ws: transport,
                                        host: parent.config.server.host,
                                        port: parent.config.server.port,
                                        open: false,
                                        allowedHosts: parent.config.server.allowedHosts,
                                        ...(parent.config.server.hmr === false ? { hmr: false } : {}),
                                    });
                                },
                                configureServer: {
                                    order: 'pre',
                                    handler(child) {
                                        startingServer = child;
                                        return () => {
                                            // Vite's middleware error handler logs errors and then calls next without the error.
                                            child.middlewares.use(
                                                (
                                                    error: unknown,
                                                    _request: IncomingMessage,
                                                    response: ServerResponse,
                                                    next: (error?: unknown) => void,
                                                ) => {
                                                    response.statusCode = 500;
                                                    next(error);
                                                },
                                            );
                                        };
                                    },
                                },
                            },
                        ],
                    });
                } catch (error) {
                    await startingServer?.close();
                    throw error;
                }

                servers.set(base, server);
            });

            // A bundle can ship both a plugin and a Meteor app; route the app's more specific prefix first.
            const routes = extensions
                .map((extension) => getExtensionBasePath(extension, true))
                .sort((left, right) => right.length - left.length);

            parent.middlewares.use((request, response, next) => {
                const base = routes.find((prefix) => request.url?.startsWith(prefix));
                if (!base) {
                    next();
                    return;
                }

                const server = servers.get(base);
                if (!server) {
                    response.writeHead(503, { 'Content-Type': 'text/plain' });
                    response.end('The extension development server could not be started. Check the watcher log.');
                    return;
                }

                server.middlewares(request, response, (error?: unknown) => {
                    if (response.writableEnded) return;
                    const status = error || response.statusCode >= 500 ? 500 : 404;
                    response.writeHead(status, { 'Content-Type': 'text/plain' });
                    response.end(
                        status === 500
                            ? 'Extension transform failed. Check the watcher log.'
                            : 'Extension resource not found.',
                    );
                });
            });
        },

        async closeBundle() {
            const group = groups.get(this.environment);
            if (!group) return;
            group.closing ??= (async () => {
                const closing = Array.from(group.servers.values());
                group.servers.clear();
                try {
                    await Promise.all(closing.map((server) => server.close()));
                } finally {
                    group.restoreListenerLimit?.();
                }
            })();
            await group.closing;
        },
    };
}
