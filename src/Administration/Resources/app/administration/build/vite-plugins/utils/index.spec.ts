/**
 * @sw-package framework
 */
import { getDevServerHttpsConfig } from './index';

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
});
