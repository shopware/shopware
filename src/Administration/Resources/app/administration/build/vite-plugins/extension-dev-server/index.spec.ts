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

    function addPlugin(name: string, technicalName: string, files: Record<string, string>): Record<string, unknown> {
        Object.entries(files).forEach(([file, content]) =>
            write(`custom/plugins/${name}/src/Resources/app/${file}`, content),
        );

        return {
            basePath: `custom/plugins/${name}/src`,
            technicalName,
            administration: {
                path: 'Resources/app/administration/src',
                entryFilePath: 'Resources/app/administration/src/main.js',
            },
        };
    }

    function serve(bundledDev: boolean): Result {
        const outputPath = path.join(projectRoot, 'result.json');
        const scriptPath = path.join(projectRoot, 'serve.cjs');
        // The bundled dev mode of Vite forces `assets/[name].js` as the name of the entry files
        const pluginEntry = bundledDev ? '/_internal_ext/alpha/assets/alpha.js' : '/_internal_ext/alpha/main.js';
        const hmrClient = bundledDev ? '/_internal_ext/alpha/bundledDevClient.mjs' : '/_internal_ext/alpha/@vite/client';

        fs.writeFileSync(
            scriptPath,
            `
            const { writeFileSync } = require('fs');

            require(${JSON.stringify(require.resolve('ts-node'))}).register({
                transpileOnly: true,
                project: ${JSON.stringify(path.join(administrationRoot, 'tsconfig.json'))},
            });

            const { loadViteToolchain } = require(${JSON.stringify(path.join(__dirname, '../vite-toolchain/index.ts'))});
            const { loadExtensions } = require(${JSON.stringify(path.join(__dirname, '../utils/index.ts'))});
            const ExtensionDevServerPlugin = require(${JSON.stringify(path.join(__dirname, 'index.ts'))}).default;

            (async () => {
                const toolchain = await loadViteToolchain();
                const server = await toolchain.vite.createServer({
                    root: ${JSON.stringify(path.join(projectRoot, 'admin/app'))},
                    configFile: false,
                    logLevel: 'silent',
                    server: { host: '127.0.0.1', port: 0 },
                    experimental: { bundledDev: ${String(bundledDev)} },
                    plugins: [ExtensionDevServerPlugin(toolchain, loadExtensions(), ${String(bundledDev)})],
                });
                await server.listen();

                const origin = () => 'http://127.0.0.1:' + server.httpServer.address().port;
                const status = async (url) => (await fetch(origin() + url)).status;
                const result = {};

                // The bundled dev mode answers with a fallback page until the first bundle is built
                for (let attempt = 0; attempt < 40 && !result.plugin?.includes('alpha plugin'); attempt += 1) {
                    result.plugin = await (await fetch(origin() + ${JSON.stringify(pluginEntry)})).text();
                    await new Promise((resolve) => setTimeout(resolve, 250));
                }
                result.meteorApp = await (await fetch(origin() + '/_internal_app/alpha/')).text();
                result.missingFile = await status('/_internal_ext/alpha/missing.js');
                result.brokenExtension = await status('/_internal_ext/broken/main.js');

                const client = await (await fetch(origin() + ${JSON.stringify(hmrClient)})).text();
                const token = (client.match(/wsToken = "([^"]+)"/) || [])[1];
                result.hmr = await new Promise((resolve) => {
                    const socket = new WebSocket(
                        origin().replace('http', 'ws') + '/_internal_ext/alpha/?token=' + token,
                        'vite-hmr',
                    );
                    socket.onmessage = (event) => {
                        socket.close();
                        resolve(JSON.parse(event.data).type);
                    };
                    socket.onerror = () => resolve('error');
                });

                await server.restart();
                result.pluginAfterRestart = await status(${JSON.stringify(pluginEntry)});

                await server.close();
                process.on('exit', () => writeFileSync(process.argv[2], JSON.stringify(result)));
            })();
            `,
        );

        execFileSync(process.execPath, [scriptPath, outputPath], {
            cwd: administrationRoot,
            env: { ...process.env, PROJECT_ROOT: projectRoot, VITE_CONFIG_NATIVE_IGNORE_WARNING: 'true' },
            stdio: 'pipe',
            timeout: 30000,
        });

        return JSON.parse(fs.readFileSync(outputPath, 'utf8')) as Result;
    }

    beforeEach(() => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'extension-dev-server-'));

        write('var/config_js_features.json', JSON.stringify({ ADMIN_VITE_8: true }));
        write('admin/app/index.html', '<script type="module" src="./main.js"></script>');
        write('admin/app/main.js', 'export default "administration";');
        write(
            'var/plugins.json',
            JSON.stringify({
                Alpha: addPlugin('Alpha', 'alpha', {
                    'administration/src/main.js': 'export default "alpha plugin";',
                    'meteor-app/index.html': '<p>alpha meteor-app</p>',
                }),
                Broken: addPlugin('Broken', 'broken', {
                    'administration/src/main.js': 'export default "broken";',
                    'administration/src/vite.config.js': 'throw new Error("broken config");',
                }),
            }),
        );
    });

    afterEach(() => {
        fs.rmSync(projectRoot, { recursive: true, force: true });
    });

    it.each([false, true])(
        'serves all extensions through the port of the Administration (bundled dev: %s)',
        (bundledDev) => {
            const { plugin, meteorApp, ...result } = serve(bundledDev);

            expect(plugin).toContain('alpha plugin');
            expect(meteorApp).toContain('/_internal_app/alpha/@vite/client');
            expect(result).toEqual({
                missingFile: 404,
                brokenExtension: 503,
                hmr: 'connected',
                pluginAfterRestart: 200,
            });
        },
        60000,
    );
});
