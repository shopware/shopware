/**
 * @sw-package framework
 */
import colors from 'picocolors';
import type { IncomingMessage } from 'http';
import type { Duplex } from 'stream';
import type { Plugin, ViteDevServer } from 'vite';
import { getExtensionDevConfig } from '../extension-config';
import { getExtensionBasePath, writePluginDevJson } from '../utils';
import type { ExtensionDefinition } from '../utils';
import type { ViteToolchain } from '../vite-toolchain';

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
    const servers = new Map<string, ViteDevServer>();
    const basePaths = extensions.map(getExtensionBasePath);
    const findBasePath = (url = '') => basePaths.find((basePath) => url.startsWith(basePath));
    let httpServer: ViteDevServer['httpServer'] = null;

    // The running extension servers answer the WebSocket upgrades of their base path themselves
    function rejectUpgradeOfFailedServer(request: IncomingMessage, socket: Duplex): void {
        const basePath = findBasePath(request.url);
        if (basePath && !servers.has(basePath)) {
            socket.destroy();
        }
    }

    return {
        name: 'shopware-vite-plugin-extension-dev-server',
        apply: 'serve',

        async configureServer(parent) {
            httpServer = parent.httpServer;
            if (!httpServer) {
                throw new Error('The extension dev servers need the HTTP server of the Administration.');
            }

            writePluginDevJson(parent.config.root, extensions, useBundledDev);

            await Promise.all(
                extensions.map(async (extension) => {
                    try {
                        const server = await createExtensionServer(toolchain, extension, useBundledDev, parent);
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

            httpServer.on('upgrade', rejectUpgradeOfFailedServer);

            parent.middlewares.use((request, response, next) => {
                const basePath = findBasePath(request.url);
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
                    response.writeHead(404, { 'Content-Type': 'text/plain' });
                    response.end();
                });
            });
        },

        async closeServer() {
            httpServer?.off('upgrade', rejectUpgradeOfFailedServer);
            await Promise.allSettled([...servers.values()].map((server) => server.close()));
            servers.clear();
        },
    };
}

// Vite copies these options of `server.hmr` to `server.ws`
const hmrSocketOptions = [
    'protocol',
    'host',
    'port',
    'clientPort',
    'path',
    'server',
] as const;

async function createExtensionServer(
    toolchain: ViteToolchain,
    extension: ExtensionDefinition,
    useBundledDev: boolean,
    parent: ViteDevServer,
): Promise<ViteDevServer> {
    const config = getExtensionDevConfig(toolchain, extension, useBundledDev);
    let startingServer: ViteDevServer | undefined;

    try {
        return await toolchain.vite.createServer({
            ...config,
            plugins: [
                ...(config.plugins ?? []),
                {
                    name: 'shopware-vite-plugin-extension-middleware',
                    // Runs before the configureServer hooks of the extension, which can fail
                    enforce: 'pre',
                    config: {
                        // Runs after the vite.config of the extension, which must not open its own port
                        order: 'post',
                        handler(extensionConfig) {
                            // A returned config would only be merged, so the socket options of the extension would stay
                            const server = (extensionConfig.server ??= {});
                            Object.assign(server, {
                                middlewareMode: true,
                                host: parent.config.server.host,
                                port: parent.config.server.port,
                                allowedHosts: parent.config.server.allowedHosts,
                                // Typed with Vite 6, which only knows `ws: false`
                                ws: { server: parent.httpServer },
                            });
                            const hmr = server.hmr;
                            if (typeof hmr === 'object') {
                                hmrSocketOptions.forEach((key) => delete hmr[key]);
                            }
                        },
                    },
                    configureServer: {
                        order: 'pre',
                        handler(server) {
                            startingServer = server;
                        },
                    },
                },
            ],
        });
    } catch (error) {
        // A server that fails in a configureServer hook already watches files
        await startingServer?.close();
        throw error;
    }
}
