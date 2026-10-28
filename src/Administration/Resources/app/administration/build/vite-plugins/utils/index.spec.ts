/**
 * @sw-package framework
 */
import fs from 'fs';
import os from 'os';
import path from 'path';
import * as net from 'net';
import {
    createPluginDevJson,
    exportViteServerMapping,
    getDevServerHttpsConfig,
    getMainViteServerConfig,
    readFeatureFlags,
    runForEachExtension,
} from './index';
import type { ExtensionDefinition } from './index';

jest.mock('net', () => ({ createServer: jest.fn() }));

describe('build/vite-plugins/utils', () => {
    describe('exportViteServerMapping', () => {
        const originalProjectRoot = process.env.PROJECT_ROOT;
        const originalAdminPort = process.env.ADMIN_PORT;
        let projectRoot: string;
        let write: jest.SpyInstance;
        let listen: jest.Mock;

        beforeEach(() => {
            projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'vite-server-mapping-'));
            process.env.PROJECT_ROOT = projectRoot;
            process.env.ADMIN_PORT = '5173';
            fs.mkdirSync(path.join(projectRoot, 'var'));
            fs.writeFileSync(
                path.join(projectRoot, 'var/plugins.json'),
                JSON.stringify(
                    Object.fromEntries(
                        Array.from({ length: 40 }, (_, index) => [
                            `Plugin${index}`,
                            {
                                basePath: `custom/plugins/Plugin${index}/src`,
                                technicalName: `plugin-${index}`,
                                administration: {
                                    path: 'Resources/app/administration/src',
                                    entryFilePath: 'Resources/app/administration/src/main.ts',
                                },
                            },
                        ]),
                    ),
                ),
            );
            write = jest.spyOn(fs, 'writeFileSync').mockImplementation(() => {});
            const server = {
                listen: jest.fn((_options, ready: () => void) => ready()),
                close: jest.fn((closed: () => void) => closed()),
                once: jest.fn(),
            };
            listen = server.listen;
            jest.mocked(net.createServer).mockReturnValue(server as unknown as net.Server);
        });

        afterEach(() => {
            jest.restoreAllMocks();
            fs.rmSync(projectRoot, { recursive: true, force: true });
            if (originalProjectRoot === undefined) delete process.env.PROJECT_ROOT;
            else process.env.PROJECT_ROOT = originalProjectRoot;
            if (originalAdminPort === undefined) delete process.env.ADMIN_PORT;
            else process.env.ADMIN_PORT = originalAdminPort;
        });

        it('allocates only the Administration port for 40 extensions on the shared listener', async () => {
            await exportViteServerMapping(true);
            expect(listen).toHaveBeenCalledTimes(1);
            expect(write).toHaveBeenCalledWith(
                expect.any(String),
                JSON.stringify({ extensions: {}, vitePort: 5173 }),
                'utf-8',
            );
        });

        it('preserves individual extension ports for the legacy watcher', async () => {
            await exportViteServerMapping();
            expect(listen).toHaveBeenCalledTimes(41);
            const calls = write.mock.calls as [string, string, string][];
            const mapping = JSON.parse(calls[0][1]) as { extensions: Record<string, { port: number }> };
            expect(Object.keys(mapping.extensions)).toHaveLength(40);
            expect(mapping.extensions['plugin-0'].port).toBe(5174);
        });
    });

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

        it('gives a plugin and its Meteor app distinct routes on the shared listener', () => {
            const appOfPlugin = { ...app, technicalName: plugin.technicalName };

            expect(createPluginDevJson([plugin, appOfPlugin], false, true)).toEqual({
                metadata: 'shopware',
                'swag-example': {
                    js: '/_internal_ext/swag-example/main.ts',
                    hmrSrc: '/_internal_ext/swag-example/@vite/client',
                    html: '/_internal_ext/swag-example/meteor-app/index.html',
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
                // eslint-disable-next-line @typescript-eslint/prefer-promise-reject-errors -- Verify diagnostics for third-party plugins rejecting with a string.
                runForEachExtension([brokenPlugin], 'build', () => Promise.reject('timeout')),
            ).resolves.toBe(true);

            expect(consoleError).toHaveBeenCalledWith(
                expect.stringContaining('# Failed to build plugin "BrokenPlugin": timeout'),
            );
        });
    });
});
