/**
 * @sw-package framework
 */
import { execFileSync } from 'child_process';
import fs from 'fs';
import os from 'os';
import path from 'path';

const administrationRoot = path.resolve(__dirname, '../../..');
const mappingPath = path.join(administrationRoot, 'build/vite-plugins/utils/vite-server-mapping.json');

type ConfigSummary = {
    bundled: boolean;
    input: Record<string, string>;
    sourcemap: boolean;
    lazy?: boolean;
};

type Result = {
    host: ConfigSummary;
    extensions: ConfigSummary[];
    manifest: Record<string, { js?: string; hmrSrc?: string; html?: string }> | null;
    sharedListenerManifest: Record<string, { js?: string; hmrSrc?: string }> | null;
    sharedListener: boolean;
    started: number;
};

describe('bundled development configuration', () => {
    let tempDir: string;
    let createdMapping: boolean;

    beforeAll(() => {
        tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'bundled-development-'));
        createdMapping = !fs.existsSync(mappingPath);
        if (createdMapping) {
            fs.writeFileSync(mappingPath, JSON.stringify({ vitePort: 5173, extensions: {} }));
        }
    });

    afterAll(() => {
        fs.rmSync(tempDir, { recursive: true, force: true });
        if (createdMapping) {
            fs.rmSync(mappingPath);
        }
    });

    it.each([
        { mode: 'core', vite8: true, command: 'serve', core: true, extensions: false },
        { mode: '1', vite8: true, command: 'serve', core: true, extensions: true },
        { mode: '0', vite8: true, command: 'serve', core: false, extensions: false },
        { mode: 'core', vite8: false, command: 'serve', core: false, extensions: false },
        { mode: 'core', vite8: true, command: 'build', core: false, extensions: false },
        { mode: '1', vite8: true, command: 'build', core: false, extensions: false },
    ])(
        'configures the host, extension servers and manifest consistently: %o',
        (testCase) => {
            const projectRoot = fs.mkdtempSync(path.join(tempDir, 'project-'));
            fs.mkdirSync(path.join(projectRoot, 'var'));
            fs.writeFileSync(
                path.join(projectRoot, 'var/config_js_features.json'),
                JSON.stringify({ ADMIN_VITE_8: testCase.vite8 }),
            );
            const fixture = path.join(projectRoot, 'example/Resources/app/administration/src');
            fs.mkdirSync(fixture, { recursive: true });
            fs.writeFileSync(path.join(fixture, 'main.ts'), 'export {};');
            fs.writeFileSync(
                path.join(projectRoot, 'var/plugins.json'),
                JSON.stringify({
                    Example: {
                        basePath: 'example',
                        technicalName: 'example',
                        administration: {
                            path: 'Resources/app/administration/src',
                            entryFilePath: 'Resources/app/administration/src/main.ts',
                        },
                    },
                }),
            );

            const scriptPath = path.join(projectRoot, 'config.cjs');
            const outputPath = path.join(projectRoot, 'result.json');
            fs.writeFileSync(
                scriptPath,
                `
            const fs = require('node:fs');
            require(${JSON.stringify(require.resolve('ts-node'))}).register({
                transpileOnly: true,
                project: ${JSON.stringify(path.join(administrationRoot, 'tsconfig.json'))},
            });
            const toolchain = require(${JSON.stringify(path.join(__dirname, 'index.ts'))});
            const utils = require(${JSON.stringify(path.join(__dirname, '../utils'))});
            const summarize = (config) => ({
                bundled: config.experimental?.bundledDev === true,
                input: config.build?.rollupOptions?.input,
                sourcemap: config.build?.sourcemap === true,
                lazy: config.build?.rollupOptions?.experimental?.devMode?.lazy,
            });
            (async () => {
                const real = await toolchain.loadViteToolchain();
                const host = await real.vite.resolveConfig({
                    configFile: ${JSON.stringify(path.join(administrationRoot, 'vite.config.mts'))},
                    logLevel: 'silent',
                }, ${JSON.stringify(testCase.command)});
                const plugin = {
                    name: 'Example', technicalName: 'example', technicalFolderName: 'example',
                    basePath: '/plugins/Example/src', path: '/plugins/Example/src/Resources/app/administration/src',
                    filePath: '/plugins/Example/src/Resources/app/administration/src/main.ts',
                    isPlugin: true, isApp: false,
                };
                const app = { ...plugin, name: 'App', technicalName: 'app', isPlugin: false, isApp: true };
                const shared = { ...plugin, name: 'Shared', technicalName: 'shared' };
                const sharedApp = { ...shared, isPlugin: false, isApp: true };
                utils.loadExtensions = () => [plugin, app, shared, sharedApp];
                utils.getViteServerPorts = () => ({ example: 5200, app: 5201, shared: 5202 });
                const configs = [];
                let started = 0;
                toolchain.loadViteToolchain = async () => ({ ...real, vite: {
                    ...real.vite,
                    createServer: async (config) => {
                        configs.push(config);
                        return { listen: async () => { started++; }, printUrls() {} };
                    },
                    build: async (config) => { configs.push(config); },
                } });
                let manifest = null;
                const writeFileSync = fs.writeFileSync;
                fs.writeFileSync = (file, data, ...args) => {
                    if (String(file).endsWith('/sw-plugin-dev.json')) {
                        manifest = JSON.parse(data);
                        return;
                    }
                    return writeFileSync(file, data, ...args);
                };
                const sharedListener = host.plugins.find((plugin) => plugin.name === 'shopware-vite-plugin-extension-dev-server');
                let sharedListenerManifest = null;
                if (sharedListener) {
                    const httpServer = require('node:http').createServer();
                    const environment = {};
                    await sharedListener.configureServer({
                        httpServer, environments: { client: environment }, config: host,
                        middlewares: { use() {} },
                    });
                    sharedListenerManifest = manifest;
                    await sharedListener.closeBundle.call({ environment });
                    manifest = null;
                }
                const runner = require(${JSON.stringify(path.join(administrationRoot, 'build/plugins.vite.ts'))});
                if (typeof runner.main === 'function') await runner.main();
                await new Promise(setImmediate);
                writeFileSync(${JSON.stringify(outputPath)}, JSON.stringify({
                    host: summarize(host), extensions: configs.map(summarize), manifest, started,
                    sharedListener: !!sharedListener, sharedListenerManifest,
                }));
            })().catch((error) => { console.error(error); process.exitCode = 1; });
            `,
            );

            execFileSync(process.execPath, [scriptPath], {
                cwd: administrationRoot,
                env: {
                    ...process.env,
                    PROJECT_ROOT: projectRoot,
                    SHOPWARE_ADMIN_BUNDLED_DEV: testCase.mode,
                    SHOPWARE_ADMIN_SKIP_SOURCEMAP_GENERATION: '0',
                    GENERATE_SOURCEMAPS: 'true',
                    VITE_MODE: testCase.command === 'serve' ? 'development' : 'production',
                    VITE_CONFIG_NATIVE_IGNORE_WARNING: 'true',
                    DISABLE_DEVSERVER_OPEN: '1',
                },
                stdio: 'pipe',
            });

            const result = JSON.parse(fs.readFileSync(outputPath, 'utf8')) as Result;
            expect(result.host.bundled).toBe(testCase.core);
            expect(result.host.input.administration).toBe(testCase.core ? 'index.html' : 'src/index.ts');
            expect(result.host.sourcemap).toBe(true);
            expect(result.extensions).toHaveLength(4);
            expect(result.extensions.map((config) => config.bundled)).toEqual([
                testCase.extensions,
                false,
                testCase.extensions,
                false,
            ]);
            expect(result.extensions[0].sourcemap).toBe(true);
            expect(result.extensions[0].lazy).toBe(testCase.extensions ? false : undefined);

            expect(result.started).toBe(testCase.command === 'build' ? 0 : 4);
            const js = testCase.extensions ? 'assets/example.js' : 'main.ts';
            const client = testCase.extensions ? 'bundledDevClient.mjs' : '@vite/client';
            expect(result.sharedListenerManifest?.example ?? null).toEqual(
                result.sharedListener
                    ? { js: `/_internal_ext/example/${js}`, hmrSrc: `/_internal_ext/example/${client}` }
                    : null,
            );
            expect(result.manifest).toEqual(
                testCase.command === 'build'
                    ? null
                    : {
                          metadata: 'shopware',
                          example: {
                              js: `/_internal_ext/example/${js}`,
                              hmrSrc: `/_internal_ext/example/${client}`,
                          },
                          app: { html: '/_internal_ext/app/index.html' },
                          shared: {
                              js: `/_internal_ext/shared/${testCase.extensions ? 'assets/shared.js' : 'main.ts'}`,
                              hmrSrc: `/_internal_ext/shared/${client}`,
                              html: '/_internal_ext/shared/index.html',
                          },
                      },
            );
        },
        120000,
    );
});
