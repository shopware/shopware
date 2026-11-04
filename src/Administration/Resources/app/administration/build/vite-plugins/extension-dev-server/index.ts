/**
 * @sw-package framework
 */
import colors from 'picocolors';
import { createHash } from 'crypto';
import fs from 'fs';
import { createServer } from 'http';
import type { IncomingMessage, Server, ServerResponse } from 'http';
import type { Duplex } from 'stream';
import type { Connect, Plugin, ResolvedConfig, ViteDevServer } from 'vite';
import { getExtensionDevConfig } from '../extension-config';
import { compareBasePathPrecedence, getExtensionBasePath, writePluginDevJson } from '../utils';
import type { ExtensionDefinition } from '../utils';
import type { ViteToolchain } from '../vite-toolchain';

// Typed with Vite 6, which does not know the `closeServer` hook of Vite 8
type Vite8Plugin = Plugin & { closeServer: (options: { reason: 'close' | 'restart' }) => Promise<void> };

type ExtensionServer = {
    signature: string;
    server: ViteDevServer;
    // Receives the WebSocket upgrades of the current Administration server, which a restart replaces
    upgrades: Server;
};

// Vite loads the config again on every restart, so the extension servers have to outlive this module
const extensionServersKey = Symbol.for('shopware-vite-plugin-extension-dev-server');

function getExtensionServers(): Map<string, ExtensionServer> {
    const store = globalThis as typeof globalThis & { [extensionServersKey]?: Map<string, ExtensionServer> };
    store[extensionServersKey] ??= new Map();

    return store[extensionServersKey];
}

// Edits to the build plugins restart the Administration server and have to reach the extension servers as well
function hashConfigDependencies(config: ResolvedConfig): string {
    const hash = createHash('sha1');
    config.configFileDependencies
        .filter((file) => file !== config.configFile)
        .sort()
        .forEach((file) => hash.update(file).update(fs.readFileSync(file)));

    return hash.digest('hex');
}

function forwardUpgrades(
    source: NonNullable<ViteDevServer['httpServer']>,
    findTarget: (request: IncomingMessage) => Server | undefined,
): void {
    function forward(request: IncomingMessage, socket: Duplex, head: Buffer): void {
        findTarget(request)?.emit('upgrade', request, socket, head);
    }

    function stop(): void {
        source.off('upgrade', forward);
    }

    source.on('upgrade', forward);
    source.once('close', stop);
}

/**
 * @private
 *
 * Runs the dev servers of the extensions as middlewares of the Administration dev server, so the watcher needs one port.
 * A restart of the Administration server keeps the extension servers whose configuration did not change.
 */
export default function ExtensionDevServerPlugin(
    toolchain: ViteToolchain,
    extensions: ExtensionDefinition[],
    useBundledDev: boolean,
): Vite8Plugin {
    const servers = getExtensionServers();

    return {
        name: 'shopware-vite-plugin-extension-dev-server',
        apply: 'serve',

        async configureServer(parent) {
            const httpServer = parent.httpServer;
            if (!httpServer) {
                throw new Error('The extension dev servers need the HTTP server of the Administration.');
            }

            writePluginDevJson(parent.config.root, extensions, useBundledDev);

            const { host, allowedHosts } = parent.config.server;
            const environment = [
                hashConfigDependencies(parent.config),
                useBundledDev,
                host,
                allowedHosts,
            ];
            const wanted = new Map(
                extensions.map((extension) => [
                    getExtensionBasePath(extension),
                    { extension, signature: JSON.stringify([extension, ...environment]) },
                ]),
            );

            await Promise.allSettled(
                [...servers]
                    .filter(([basePath, { signature }]) => wanted.get(basePath)?.signature !== signature)
                    .map(([basePath, { server }]) => {
                        servers.delete(basePath);
                        return server.close();
                    }),
            );

            await Promise.all(
                [...wanted]
                    .filter(([basePath]) => !servers.has(basePath))
                    .map(async ([basePath, { extension, signature }]) => {
                        try {
                            const upgrades = createServer();
                            const server = await createExtensionServer(
                                toolchain,
                                extension,
                                useBundledDev,
                                parent,
                                upgrades,
                            );
                            servers.set(basePath, { signature, server, upgrades });
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

            const basePaths = [...wanted.keys()].sort(compareBasePathPrecedence);
            const findBasePath = (url = '') => basePaths.find((candidate) => url.startsWith(candidate));

            forwardUpgrades(httpServer, (request) => servers.get(findBasePath(request.url) ?? '')?.upgrades);

            parent.middlewares.use((request, response, next) => {
                const basePath = findBasePath(request.url);
                if (!basePath) {
                    next();
                    return;
                }

                const server = servers.get(basePath)?.server;
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

        async closeServer({ reason }) {
            if (reason === 'restart') {
                return;
            }

            const closing = [...servers.values()].map(({ server }) => server.close());
            servers.clear();
            await Promise.allSettled(closing);
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
    upgrades: Server,
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
                                middlewareMode: { server: upgrades },
                                host: parent.config.server.host,
                                allowedHosts: parent.config.server.allowedHosts,
                                // Typed with Vite 6, which only knows `ws: false`
                                ws: { server: upgrades },
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
        // A server that fails in a configureServer hook already watches files
        await startingServer?.close();
        throw error;
    }
}
