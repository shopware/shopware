/**
 * @sw-package framework
 */
import fs from 'fs';
import os from 'os';
import path from 'path';
import { createPluginDevJson, getDevServerHttpsConfig, readFeatureFlags } from './index';
import type { ExtensionDefinition } from './index';

describe('build/vite-plugins/utils', () => {
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

        it('passes the configured certificate and key files to Vite', () => {
            expect(
                getDevServerHttpsConfig({
                    SHOPWARE_ADMIN_HTTPS_CERTIFICATE_FILE: '/certs/cert.pem',
                    SHOPWARE_ADMIN_HTTPS_KEY_FILE: '/certs/key.pem',
                }),
            ).toEqual({ cert: '/certs/cert.pem', key: '/certs/key.pem' });
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

        it('only contains the metadata without extensions', () => {
            expect(createPluginDevJson([], true)).toEqual({ metadata: 'shopware' });
        });
    });
});
