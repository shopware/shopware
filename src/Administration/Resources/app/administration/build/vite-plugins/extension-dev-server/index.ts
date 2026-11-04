/**
 * @sw-package framework
 */
import colors from 'picocolors';
import type { IncomingMessage, ServerResponse } from 'http';
import type { Connect, Plugin, UserConfig, ViteDevServer } from 'vite';
import { getExtensionDevConfig } from '../extension-config';
import { compareBasePathPrecedence, getExtensionBasePath, writePluginDevJson } from '../utils';
import type { ExtensionDefinition } from '../utils';
import type { ViteToolchain } from '../vite-toolchain';

type HttpServer = NonNullable<ViteDevServer['httpServer']>;

// Typed with Vite 6, which does not know the `closeServer` hook of Vite 8
type Vite8Plugin = Plugin & { closeServer: () => Promise<void> };

/**
 * @private
 *
 * Runs the dev servers of the extensions as middlewares of the Administration dev server, so the watcher needs one port.
 */
export default function ExtensionDevServerPlugin(
    toolchain: ViteToolchain,
    extensions: ExtensionDefinition[],
    useBundledDev: boolean,
): Vite8Plugin {
    // A restart configures the new server before it closes the old one, and both can use this plugin instance
    const generations: Map<string, ViteDevServer>[] = [];

    return {
        name: 'shopware-vite-plugin-extension-dev-server',
        apply: 'serve',

        async configureServer(parent) {
            const httpServer = parent.httpServer;
            if (!httpServer) {
                throw new Error('The extension dev servers need the HTTP server of the Administration.');
            }

            const servers = new Map<string, ViteDevServer>();
            generations.push(servers);

            // Each extension listens for the WebSocket upgrades of its HMR client, 0 means no limit
            const maxListeners = httpServer.getMaxListeners();
            if (maxListeners > 0) {
                httpServer.setMaxListeners(maxListeners + extensions.length);
            }

            writePluginDevJson(parent.config.root, extensions, useBundledDev);

            await Promise.all(
                extensions.map(async (extension) => {
                    try {
                        const server = await createExtensionServer(toolchain, extension, useBundledDev, parent, httpServer);
                        servers.set(getExtensionBasePath(extension), server);
                    } catch (error) {
                        const message = error instanceof Error ? error.message : String(error);
                        parent.config.logger.error(
                            colors.red(
                                `# Failed to start ${extension.isApp ? 'app' : 'plugin'} "${extension.name}": ${message}`,
                            ),
                        );
                    }
                }),
            );

            const basePaths = extensions.map(getExtensionBasePath).sort(compareBasePathPrecedence);

            parent.middlewares.use((request, response, next) => {
                const basePath = basePaths.find((candidate) => request.url?.startsWith(candidate));
                if (!basePath) {
                    next();
                    return;
                }

                const server = servers.get(basePath);
                if (!server) {
                    response.writeHead(503, { 'Content-Type': 'text/plain' });
                    response.end('The dev server of this extension did not start. Check the log of the watcher.');
                    return;
                }

                server.middlewares(request, response, () => {
                    response.writeHead(response.statusCode >= 500 ? 500 : 404, { 'Content-Type': 'text/plain' });
                    response.end();
                });
            });
        },

        async closeServer() {
            const servers = generations.shift();
            if (!servers) {
                return;
            }

            await Promise.allSettled([...servers.values()].map((server) => server.close()));
        },
    };
}

async function createExtensionServer(
    toolchain: ViteToolchain,
    extension: ExtensionDefinition,
    useBundledDev: boolean,
    parent: ViteDevServer,
    httpServer: HttpServer,
): Promise<ViteDevServer> {
    const config = getExtensionDevConfig(toolchain, extension, useBundledDev);
    const serverOptions: UserConfig['server'] = {
        middlewareMode: { server: httpServer },
        host: parent.config.server.host,
        allowedHosts: parent.config.server.allowedHosts,
    };
    // Typed with Vite 6, which only knows `ws: false`
    Object.assign(serverOptions, { ws: { server: httpServer } });
    let startingServer: ViteDevServer | undefined;

    try {
        return await toolchain.vite.createServer({
            ...config,
            plugins: [
                ...(config.plugins ?? []),
                {
                    name: 'shopware-vite-plugin-extension-middleware',
                    // Runs after the vite.config of the extension, which must not open its own port
                    enforce: 'post',
                    config: () => ({ server: serverOptions }),
                    configureServer: {
                        order: 'pre',
                        handler(server) {
                            startingServer = server;

                            // The error middleware of Vite only logs in middleware mode and passes on without the error
                            return () => {
                                server.middlewares.use(
                                    (
                                        error: unknown,
                                        _request: IncomingMessage,
                                        response: ServerResponse,
                                        next: Connect.NextFunction,
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
        // A server that fails after its configureServer hooks already listens on the shared HTTP server
        await startingServer?.close();
        throw error;
    }
}
