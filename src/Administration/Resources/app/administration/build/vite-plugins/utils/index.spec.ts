/**
 * @sw-package framework
 */
import fs from 'fs';
import os from 'os';
import path from 'path';
import { getDevServerHttpsConfig, readFeatureFlags } from './index';

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
});
