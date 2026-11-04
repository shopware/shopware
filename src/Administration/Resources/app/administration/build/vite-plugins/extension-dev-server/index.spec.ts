/**
 * @sw-package framework
 */
import { execFileSync } from 'child_process';
import fs from 'fs';
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
            // Counts the starts of the dev server and fails while the marker file exists.
            // The socket options must not move the HMR client away from the port of the Administration.
            'administration/src/vite.config.js': `
                import { appendFileSync, existsSync } from 'fs';
                export default {
                    server: { hmr: { port: 24679 }, ws: { host: 'hmr.invalid', protocol: 'wss' } },
                    plugins: [{
                        name: 'count-starts',
                        configureServer: {
                            order: 'pre',
                            handler() {
                                appendFileSync(${JSON.stringify(path.join(projectRoot, `${technicalName}.starts`))}, 'x');
                                if (existsSync(${JSON.stringify(path.join(projectRoot, `${technicalName}.fail`))})) {
                                    throw new Error('failed');
                                }
                            },
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

    function serve(bundledDev: boolean): Result {
        const outputPath = path.join(projectRoot, 'result.json');
        const scriptPath = path.join(projectRoot, 'serve.cjs');
        // The bundled dev mode of Vite forces `assets/[name].js` as the name of the entry files
        const entry = (name: string) =>
            bundledDev ? `/_internal_ext/${name}/assets/${name}.js` : `/_internal_ext/${name}/main.js`;
        const hmrClient = (name: string) =>
            bundledDev ? `/_internal_ext/${name}/bundledDevClient.mjs` : `/_internal_ext/${name}/@vite/client`;

        write('admin/app/options.js', `export const bundledDev = ${String(bundledDev)};`);
        write(
            'admin/app/vite.config.mjs',
            `
            import { loadViteToolchain } from ${JSON.stringify(path.join(__dirname, '../vite-toolchain/index.ts'))};
            import { loadExtensions } from ${JSON.stringify(path.join(__dirname, '../utils/index.ts'))};
            import ExtensionDevServerPlugin from ${JSON.stringify(path.join(__dirname, 'index.ts'))};
            import { bundledDev } from './options.js';

            export default async () => ({
                logLevel: 'silent',
                server: { host: '127.0.0.1', port: 0 },
                experimental: { bundledDev },
                plugins: [ExtensionDevServerPlugin(await loadViteToolchain(), loadExtensions(), bundledDev)],
            });
            `,
        );

        fs.writeFileSync(
            scriptPath,
            `
            const { appendFileSync, readFileSync, rmSync, writeFileSync } = require('fs');
            const path = require('path');

            require(${JSON.stringify(require.resolve('ts-node'))}).register({
                transpileOnly: true,
                project: ${JSON.stringify(path.join(administrationRoot, 'tsconfig.json'))},
            });

            const { loadViteToolchain } = require(${JSON.stringify(path.join(__dirname, '../vite-toolchain/index.ts'))});
            const project = (file) => path.join(${JSON.stringify(projectRoot)}, file);
            const starts = (name) => readFileSync(project(name + '.starts'), 'utf8').length;

            (async () => {
                const toolchain = await loadViteToolchain();
                const server = await toolchain.vite.createServer({ configFile: project('admin/app/vite.config.mjs') });
                await server.listen();

                const origin = () => 'http://127.0.0.1:' + server.httpServer.address().port;
                const status = async (url) => (await fetch(origin() + url)).status;
                const text = async (url) => (await fetch(origin() + url)).text();
                const hmr = async (name) => {
                    const client = await text(${JSON.stringify(hmrClient('NAME'))}.replace('NAME', name));
                    const token = (client.match(/wsToken = "([^"]+)"/) || [])[1];
                    // Runs the lines of the client that resolve the socket address from the options of the dev server
                    const socketLines = client.match(/const socketProtocol = [^;]+;\\s*const hmrPort = [^;]+;\\s*const socketHost = [^;]+;/)[0];
                    const socketUrl = new Function('importMetaUrl', socketLines + ' return socketProtocol + "://" + socketHost;');
                    return new Promise((resolve) => {
                        const socket = new WebSocket(
                            socketUrl(new URL(origin() + '/')) + '?token=' + token,
                            'vite-hmr',
                        );
                        socket.onmessage = (event) => {
                            socket.close();
                            resolve(JSON.parse(event.data).type);
                        };
                        socket.onerror = () => resolve('error');
                    });
                };
                const result = {};

                // The bundled dev mode answers with a fallback page until the first bundle is built
                for (let attempt = 0; attempt < 40 && !result.alpha?.includes('alpha plugin'); attempt += 1) {
                    result.alpha = await text(${JSON.stringify(entry('alpha'))});
                    await new Promise((resolve) => setTimeout(resolve, 250));
                }
                result.meteorApp = await text('/_internal_app/alpha/');
                result.missingFile = await status('/_internal_ext/alpha/missing.js');
                result.failing = await status(${JSON.stringify(entry('failing'))});
                result.hmr = await hmr('alpha');

                rmSync(project('failing.fail'));
                const { Removed, ...plugins } = JSON.parse(readFileSync(project('var/plugins.json'), 'utf8'));
                writeFileSync(project('var/plugins.json'), JSON.stringify(plugins));
                await server.restart();
                result.removedServedAfterRestart = (await text(${JSON.stringify(entry('removed'))})).includes('removed plugin');
                result.failingAfterRestart = await status(${JSON.stringify(entry('failing'))});
                result.hmrAfterRestart = await hmr('alpha');
                result.failingHmrAfterRestart = await hmr('failing');
                result.alphaStartsAfterRestart = starts('alpha');

                appendFileSync(project('admin/app/options.js'), '// changed');
                await server.restart();
                result.alphaStartsAfterDependencyChange = starts('alpha');

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
        writePlugins(['Alpha', 'Failing', 'Removed']);
        writePlugin('Alpha', { 'meteor-app/index.html': '<p>alpha meteor-app</p>' });
    });

    afterEach(() => {
        fs.rmSync(projectRoot, { recursive: true, force: true });
    });

    it.each([false, true])(
        'serves all extensions through the port of the Administration and keeps them on restart (bundled dev: %s)',
        (bundledDev) => {
            const { alpha, meteorApp, ...result } = serve(bundledDev);

            expect(alpha).toContain('alpha plugin');
            expect(meteorApp).toContain('/_internal_app/alpha/@vite/client');
            expect(result).toEqual({
                missingFile: 404,
                failing: 503,
                hmr: 'connected',
                removedServedAfterRestart: false,
                failingAfterRestart: 200,
                hmrAfterRestart: 'connected',
                failingHmrAfterRestart: 'connected',
                alphaStartsAfterRestart: 1,
                alphaStartsAfterDependencyChange: 2,
            });
        },
        90000,
    );
});
