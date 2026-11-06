/**
 * @sw-package framework
 */
import { execFileSync } from 'child_process';
import fs from 'fs';
import net from 'net';
import os from 'os';
import path from 'path';

const administrationRoot = path.resolve(__dirname, '../../..');

type Result = Record<string, number | string | boolean>;

/**
 * Vite 8 is published as ESM only, which Jest cannot load, so the dev servers run in a separate Node.js process.
 * The process only exits on its own when closing the Administration server also closed every extension server.
 */
describe('build/vite-plugins/extension-dev-server', () => {
    let projectRoot: string;

    function write(file: string, content: string): void {
        fs.mkdirSync(path.dirname(path.join(projectRoot, file)), { recursive: true });
        fs.writeFileSync(path.join(projectRoot, file), content);
    }

    function writePlugin(name: string, files: Record<string, string>): Record<string, unknown> {
        const technicalName = name.toLowerCase();
        Object.entries({
            'administration/src/main.js': `export default "${technicalName} plugin";`,
            // Fails while the marker file exists.
            // The socket options must not move the HMR client away from the port of the Administration.
            'administration/src/vite.config.js': `
                import { existsSync } from 'fs';
                export default {
                    server: { hmr: { port: 24679 }, ws: { host: 'hmr.invalid', protocol: 'wss' } },
                    plugins: [{
                        name: 'fail-on-marker',
                        configureServer(server) {
                            if (existsSync(${JSON.stringify(path.join(projectRoot, `${technicalName}.fail`))})) {
                                throw new Error('failed');
                            }
                            server.middlewares.use((request, response, next) => {
                                next(request.url.endsWith('/broken.js') ? new Error('broken') : undefined);
                            });
                        },
                    }],
                };
            `,
            ...files,
        }).forEach(([file, content]) => write(`custom/plugins/${name}/src/Resources/app/${file}`, content));

        return {
            basePath: `custom/plugins/${name}/src`,
            technicalName,
            administration: {
                path: 'Resources/app/administration/src',
                entryFilePath: 'Resources/app/administration/src/main.js',
            },
        };
    }

    function writePlugins(names: string[]): void {
        write('var/plugins.json', JSON.stringify(Object.fromEntries(names.map((name) => [name, writePlugin(name, {})]))));
    }

    async function findFreePort(): Promise<number> {
        const server = net.createServer();
        await new Promise<void>((resolve) => {
            server.listen(0, '127.0.0.1', resolve);
        });
        const { port } = server.address() as net.AddressInfo;
        await new Promise((resolve) => {
            server.close(resolve);
        });

        return port;
    }

    function serve(bundledDev: boolean, port: number): Result {
        const outputPath = path.join(projectRoot, 'result.json');
        const scriptPath = path.join(projectRoot, 'serve.cjs');
        // The bundled dev mode of Vite forces `assets/[name].js` as the name of the entry files
        const entry = (name: string) =>
            bundledDev ? `/_internal_ext/${name}/assets/${name}.js` : `/_internal_ext/${name}/main.js`;
        const hmrClient = bundledDev ? '/_internal_ext/alpha/bundledDevClient.mjs' : '/_internal_ext/alpha/@vite/client';

        write(
            'admin/app/vite.config.mjs',
            `
            import { loadViteToolchain } from ${JSON.stringify(path.join(__dirname, '../vite-toolchain/index.ts'))};
            import { loadExtensions } from ${JSON.stringify(path.join(__dirname, '../utils/index.ts'))};
            import ExtensionDevServerPlugin from ${JSON.stringify(path.join(__dirname, 'index.ts'))};

            export default async () => ({
                logLevel: 'silent',
                server: { host: '127.0.0.1', port: ${port}, strictPort: true },
                experimental: { bundledDev: ${String(bundledDev)} },
                plugins: [ExtensionDevServerPlugin(await loadViteToolchain(), loadExtensions(), ${String(bundledDev)})],
            });
            `,
        );

        fs.writeFileSync(
            scriptPath,
            `
            const { writeFileSync } = require('fs');
            const path = require('path');

            require(${JSON.stringify(require.resolve('ts-node'))}).register({
                transpileOnly: true,
                project: ${JSON.stringify(path.join(administrationRoot, 'tsconfig.json'))},
            });

            const { loadViteToolchain } = require(${JSON.stringify(path.join(__dirname, '../vite-toolchain/index.ts'))});
            const origin = 'http://127.0.0.1:${port}';
            const status = async (url) => (await fetch(origin + url)).status;
            const text = async (url) => (await fetch(origin + url)).text();
            const hmr = async (basePath) => {
                const token = (await text(${JSON.stringify(hmrClient)})).match(/wsToken = "([^"]+)"/)[1];
                return connect(basePath, token);
            };
            const connect = (basePath, token) => new Promise((resolve) => {
                const socket = new WebSocket('ws://127.0.0.1:${port}' + basePath + '?token=' + token, 'vite-hmr');
                socket.onmessage = (event) => {
                    socket.close();
                    resolve(JSON.parse(event.data).type);
                };
                socket.onerror = () => resolve('closed');
            });

            (async () => {
                const toolchain = await loadViteToolchain();
                const server = await toolchain.vite.createServer({
                    configFile: path.join(${JSON.stringify(projectRoot)}, 'admin/app/vite.config.mjs'),
                });
                await server.listen();
                const result = {};

                // The bundled dev mode answers with a fallback page until the first bundle is built
                for (let attempt = 0; attempt < 40 && !result.alpha?.includes('alpha plugin'); attempt += 1) {
                    result.alpha = await text(${JSON.stringify(entry('alpha'))});
                    await new Promise((resolve) => setTimeout(resolve, 250));
                }
                result.meteorApp = await text('/_internal_app/alpha/');
                result.missingFile = await status('/_internal_ext/alpha/missing.js');
                result.brokenFile = await status('/_internal_ext/alpha/broken.js');
                result.failing = await status(${JSON.stringify(entry('failing'))});

                result.hmrFallbackOnAdministrationPort = (await text(${JSON.stringify(hmrClient)})).includes(
                    '127.0.0.1:${port}/_internal_ext/alpha/',
                );
                result.hmr = await hmr('/_internal_ext/alpha/');
                result.failingHmr = await connect('/_internal_ext/failing/', '');

                await server.restart();
                result.hmrAfterRestart = await hmr('/_internal_ext/alpha/');

                await server.close();
                process.on('exit', () => writeFileSync(process.argv[2], JSON.stringify(result)));
            })();
            `,
        );

        execFileSync(process.execPath, [scriptPath, outputPath], {
            cwd: administrationRoot,
            env: { ...process.env, PROJECT_ROOT: projectRoot, VITE_CONFIG_NATIVE_IGNORE_WARNING: 'true' },
            stdio: 'pipe',
            timeout: 45000,
        });

        return JSON.parse(fs.readFileSync(outputPath, 'utf8')) as Result;
    }

    beforeEach(() => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'extension-dev-server-'));

        write('var/config_js_features.json', JSON.stringify({ ADMIN_VITE_8: true }));
        write('admin/app/index.html', '<script type="module" src="./main.js"></script>');
        write('admin/app/main.js', 'export default "administration";');
        write('failing.fail', '');
        writePlugins(['Alpha', 'Failing']);
        writePlugin('Alpha', { 'meteor-app/index.html': '<p>alpha meteor-app</p>' });
    });

    afterEach(() => {
        fs.rmSync(projectRoot, { recursive: true, force: true });
    });

    it.each([false, true])(
        'serves all extensions through the port of the Administration (bundled dev: %s)',
        async (bundledDev) => {
            const { alpha, meteorApp, ...result } = serve(bundledDev, await findFreePort());

            expect(alpha).toContain('alpha plugin');
            expect(meteorApp).toContain('/_internal_app/alpha/@vite/client');
            expect(result).toEqual({
                missingFile: 404,
                brokenFile: 500,
                failing: 503,
                hmrFallbackOnAdministrationPort: true,
                hmr: 'connected',
                failingHmr: 'closed',
                hmrAfterRestart: 'connected',
            });
        },
        90000,
    );
});
