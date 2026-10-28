/**
 * @sw-package framework
 */
import fs from 'fs';
import path from 'path';
import type { HmrOptions, Plugin, ViteDevServer } from 'vite';
import { getExtensionDevConfig } from '../../plugins.vite';
import { createPluginDevJson, getExtensionBasePath, runForEachExtension } from '../utils';
import type { ExtensionDefinition } from '../utils';
import type { ViteToolchain } from '../vite-toolchain';

/** @private */
export default function ExtensionDevServerPlugin(
    toolchain: ViteToolchain,
    extensions: ExtensionDefinition[],
    useBundledDev: boolean,
): Plugin {
    const servers = new Map<string, ViteDevServer>();
    let restoreListenerLimit: (() => void) | undefined;

    return {
        name: 'shopware-vite-plugin-extension-dev-server',
        apply: 'serve',

        async configureServer(parent) {
            const httpServer = parent.httpServer;
            if (!httpServer) {
                throw new Error('The Administration extension watcher requires an HTTP server.');
            }

            // Each extension adds an upgrade handler for its own WebSocket path to this HTTP server.
            const listenerLimit = httpServer.getMaxListeners();
            if (listenerLimit > 0) {
                httpServer.setMaxListeners(listenerLimit + extensions.length);
                restoreListenerLimit = () => httpServer.setMaxListeners(listenerLimit);
            }

            // Middleware servers must share the parent's transport, otherwise Vite opens a separate WebSocket port.
            const parentTransport = (parent.config.server as { ws?: HmrOptions | false }).ws;
            const transport: HmrOptions | false =
                parentTransport === false
                    ? false
                    : { ...parentTransport, server: httpServer, port: undefined, path: undefined };

            fs.writeFileSync(
                path.resolve(__dirname, '../../../../../sw-plugin-dev.json'),
                JSON.stringify(createPluginDevJson(extensions, useBundledDev, true)),
            );

            await runForEachExtension(extensions, 'start', async (extension) => {
                const base = getExtensionBasePath(extension, true);
                const config = getExtensionDevConfig(toolchain, extension, useBundledDev);

                const server = await toolchain.vite.createServer({
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
                        },
                    ],
                });

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

                server.middlewares(request, response, next);
            });
        },

        async closeBundle() {
            const closing = Array.from(servers.values());
            servers.clear();
            try {
                await Promise.all(closing.map((server) => server.close()));
            } finally {
                restoreListenerLimit?.();
                restoreListenerLimit = undefined;
            }
        },
    };
}
