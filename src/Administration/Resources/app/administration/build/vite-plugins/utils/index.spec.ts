/**
 * @sw-package framework
 */
import fs from 'fs';
import os from 'os';
import path from 'path';
import {
    createPluginDevJson,
    getDevServerHttpsConfig,
    getMainViteServerConfig,
    readFeatureFlags,
    runForEachExtension,
} from './index';
import type { ExtensionDefinition } from './index';

describe('build/vite-plugins/utils', () => {
    describe('getMainViteServerConfig', () => {
        afterEach(() => {
            jest.restoreAllMocks();
        });

        it('targets the IPv4 listener of each extension even when localhost resolves to the main server', () => {
            jest.spyOn(fs, 'existsSync').mockReturnValue(true);
            jest.spyOn(fs, 'readFileSync').mockReturnValue(
                JSON.stringify({
                    vitePort: 5173,
                    extensions: {
                        storefront: { basePath: '/_internal_ext/storefront/', port: 5174 },
                        'example-app': { basePath: '/_internal_ext/example-app/', port: 5175 },
                    },
                }),
            );

            expect(getMainViteServerConfig()).toEqual({
                port: 5173,
                proxy: {
                    '/_internal_ext/storefront/': {
                        target: 'http://127.0.0.1:5174',
                        changeOrigin: true,
                        ws: true,
                        rewriteWsOrigin: true,
                    },
                    '/_internal_ext/example-app/': {
                        target: 'http://127.0.0.1:5175',
                        changeOrigin: true,
                        ws: true,
                        rewriteWsOrigin: true,
                    },
                },
            });
        });
    });

    describe('getDevServerHttpsConfig', () => {
        it('serves plain HTTP without a configured certificate', () => {
            expect(getDevServerHttpsConfig({})).toBeUndefined();
        });

        it.each([
            { SHOPWARE_ADMIN_HTTPS_CERTIFICATE_FILE: '/certs/cert.pem' },
            { SHOPWARE_ADMIN_HTTPS_KEY_FILE: '/certs/key.pem' },
            { SHOPWARE_ADMIN_HTTPS_CERTIFICATE_FILE: '/certs/cert.pem', SHOPWARE_ADMIN_HTTPS_KEY_FILE: '' },
        ])('serves plain HTTP when only one of the files is configured: %o', (env) => {
            expect(getDevServerHttpsConfig(env)).toBeUndefined();
        });

        it('passes absolute certificate and key files unchanged to Vite', () => {
            expect(
                getDevServerHttpsConfig({
                    PROJECT_ROOT: '/project',
                    SHOPWARE_ADMIN_HTTPS_CERTIFICATE_FILE: '/certs/cert.pem',
                    SHOPWARE_ADMIN_HTTPS_KEY_FILE: '/certs/key.pem',
                }),
            ).toEqual({ cert: '/certs/cert.pem', key: '/certs/key.pem' });
        });

        it('resolves relative certificate and key files from the project root', () => {
            expect(
                getDevServerHttpsConfig({
                    PROJECT_ROOT: '/project',
                    SHOPWARE_ADMIN_HTTPS_CERTIFICATE_FILE: 'certs/cert.pem',
                    SHOPWARE_ADMIN_HTTPS_KEY_FILE: './certs/key.pem',
                }),
            ).toEqual({ cert: '/project/certs/cert.pem', key: '/project/certs/key.pem' });
        });

        it('resolves relative certificate and key files from the working directory without a project root', () => {
            expect(
                getDevServerHttpsConfig({
                    SHOPWARE_ADMIN_HTTPS_CERTIFICATE_FILE: 'certs/cert.pem',
                    SHOPWARE_ADMIN_HTTPS_KEY_FILE: 'certs/key.pem',
                }),
            ).toEqual({
                cert: path.join(process.cwd(), 'certs/cert.pem'),
                key: path.join(process.cwd(), 'certs/key.pem'),
            });
        });

        it('reads the files from the environment of the process by default', () => {
            const originalCert = process.env.SHOPWARE_ADMIN_HTTPS_CERTIFICATE_FILE;
            const originalKey = process.env.SHOPWARE_ADMIN_HTTPS_KEY_FILE;
            process.env.SHOPWARE_ADMIN_HTTPS_CERTIFICATE_FILE = '/env/cert.pem';
            process.env.SHOPWARE_ADMIN_HTTPS_KEY_FILE = '/env/key.pem';

            try {
                expect(getDevServerHttpsConfig()).toEqual({ cert: '/env/cert.pem', key: '/env/key.pem' });
            } finally {
                if (originalCert === undefined) {
                    delete process.env.SHOPWARE_ADMIN_HTTPS_CERTIFICATE_FILE;
                } else {
                    process.env.SHOPWARE_ADMIN_HTTPS_CERTIFICATE_FILE = originalCert;
                }

                if (originalKey === undefined) {
                    delete process.env.SHOPWARE_ADMIN_HTTPS_KEY_FILE;
                } else {
                    process.env.SHOPWARE_ADMIN_HTTPS_KEY_FILE = originalKey;
                }
            }
        });
    });

    describe('readFeatureFlags', () => {
        const originalProjectRoot = process.env.PROJECT_ROOT;
        let projectRoot: string;

        beforeEach(() => {
            projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'read-feature-flags-'));
            process.env.PROJECT_ROOT = projectRoot;
        });

        afterEach(() => {
            fs.rmSync(projectRoot, { recursive: true, force: true });

            if (originalProjectRoot === undefined) {
                delete process.env.PROJECT_ROOT;
            } else {
                process.env.PROJECT_ROOT = originalProjectRoot;
            }
        });

        it('returns no flags when the feature flags are not dumped', () => {
            expect(readFeatureFlags()).toEqual({});
        });

        it('returns the dumped feature flags of the project', () => {
            fs.mkdirSync(path.join(projectRoot, 'var'));
            fs.writeFileSync(
                path.join(projectRoot, 'var', 'config_js_features.json'),
                JSON.stringify({ ADMIN_VITE_8: true, V6_8_0_0: false }),
            );

            expect(readFeatureFlags()).toEqual({ ADMIN_VITE_8: true, V6_8_0_0: false });
        });
    });

    describe('createPluginDevJson', () => {
        const plugin: ExtensionDefinition = {
            name: 'SwagExample',
            technicalName: 'swag-example',
            technicalFolderName: 'swagexample',
            basePath: '/plugins/SwagExample/src',
            path: '/plugins/SwagExample/src/Resources/app/administration/src',
            filePath: '/plugins/SwagExample/src/Resources/app/administration/src/main.ts',
            isPlugin: true,
            isApp: false,
        };
        const app: ExtensionDefinition = {
            name: 'ExampleApp',
            technicalName: 'example-app',
            technicalFolderName: 'exampleapp',
            basePath: '/apps/ExampleApp',
            path: '/apps/ExampleApp/Resources/app/administration/src',
            filePath: '/apps/ExampleApp/Resources/app/administration/src/main.ts',
            isPlugin: false,
            isApp: true,
        };

        it('loads the entry files of plugins through the Vite client without the bundled dev mode', () => {
            expect(createPluginDevJson([plugin, app], false)).toEqual({
                metadata: 'shopware',
                'swag-example': {
                    js: '/_internal_ext/swag-example/main.ts',
                    hmrSrc: '/_internal_ext/swag-example/@vite/client',
                },
                'example-app': {
                    html: '/_internal_ext/example-app/index.html',
                },
            });
        });

        it('loads the bundles of plugins through the bundled dev client in the bundled dev mode', () => {
            expect(createPluginDevJson([plugin, app], true)).toEqual({
                metadata: 'shopware',
                'swag-example': {
                    js: '/_internal_ext/swag-example/assets/swag-example.js',
                    hmrSrc: '/_internal_ext/swag-example/bundledDevClient.mjs',
                },
                'example-app': {
                    html: '/_internal_ext/example-app/index.html',
                },
            });
        });

        it('keeps the entry files of the plugin when the bundle also ships an app', () => {
            const appOfPlugin: ExtensionDefinition = {
                ...plugin,
                path: '/plugins/SwagExample/src/Resources/app/meteor-app',
                filePath: '/plugins/SwagExample/src/Resources/app/meteor-app/index.html',
                isPlugin: false,
                isApp: true,
            };

            expect(createPluginDevJson([plugin, appOfPlugin], false)).toEqual({
                metadata: 'shopware',
                'swag-example': {
                    js: '/_internal_ext/swag-example/main.ts',
                    hmrSrc: '/_internal_ext/swag-example/@vite/client',
                    html: '/_internal_ext/swag-example/index.html',
                },
            });
        });

        it('only contains the metadata without extensions', () => {
            expect(createPluginDevJson([], true)).toEqual({ metadata: 'shopware' });
        });
    });

    describe('runForEachExtension', () => {
        const createExtension = (name: string, isPlugin: boolean): ExtensionDefinition => ({
            name,
            technicalName: name.toLowerCase(),
            technicalFolderName: name.toLowerCase(),
            basePath: `/extensions/${name}`,
            path: `/extensions/${name}/Resources/app/administration/src`,
            filePath: `/extensions/${name}/Resources/app/administration/src/main.ts`,
            isPlugin,
            isApp: !isPlugin,
        });
        const brokenApp = createExtension('BrokenApp', false);
        const brokenPlugin = createExtension('BrokenPlugin', true);
        const workingPlugin = createExtension('WorkingPlugin', true);
        const workingApp = createExtension('WorkingApp', false);

        let consoleError: jest.SpyInstance;

        beforeEach(() => {
            consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
        });

        afterEach(() => {
            consoleError.mockRestore();
        });

        it('runs the action for every extension and reports no failure', async () => {
            const action = jest.fn(() => Promise.resolve());

            await expect(runForEachExtension([workingPlugin, workingApp], 'start', action)).resolves.toBe(false);

            expect(action.mock.calls).toEqual([[workingPlugin], [workingApp]]);
            expect(consoleError).not.toHaveBeenCalled();
        });

        it('logs a failing extension and continues with the next ones', async () => {
            const started: string[] = [];

            const hasFailed = await runForEachExtension([brokenApp, brokenPlugin, workingPlugin], 'start', (extension) => {
                if (extension.name.startsWith('Broken')) {
                    return Promise.reject(new Error(`Cannot load the vite.config of ${extension.name}`));
                }

                started.push(extension.name);
                return Promise.resolve();
            });

            expect(hasFailed).toBe(true);
            expect(started).toEqual(['WorkingPlugin']);
            expect(consoleError).toHaveBeenCalledTimes(2);
            expect(consoleError).toHaveBeenNthCalledWith(
                1,
                expect.stringContaining('# Failed to start app "BrokenApp": Cannot load the vite.config of BrokenApp'),
            );
            expect(consoleError).toHaveBeenNthCalledWith(
                2,
                expect.stringContaining(
                    '# Failed to start plugin "BrokenPlugin": Cannot load the vite.config of BrokenPlugin',
                ),
            );
        });

        it('logs rejections that are no errors', async () => {
            await expect(
                // eslint-disable-next-line @typescript-eslint/prefer-promise-reject-errors
                runForEachExtension([brokenPlugin], 'build', () => Promise.reject('timeout')),
            ).resolves.toBe(true);

            expect(consoleError).toHaveBeenCalledWith(
                expect.stringContaining('# Failed to build plugin "BrokenPlugin": timeout'),
            );
        });
    });
});
