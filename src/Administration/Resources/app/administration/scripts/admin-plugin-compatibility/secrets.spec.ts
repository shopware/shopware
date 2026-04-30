/**
 * @sw-package framework
 */

import { redactSecrets } from './secrets';

describe('admin-plugin-compatibility secret redaction', () => {
    it('redacts license keys and secret-like values', () => {
        const redacted = redactSecrets(
            'licenseKey: abcdefghijkl token=shh password="secret" key ABCDEFGH-1234-5678-ABCD',
        );

        expect(redacted).toContain('licenseKey: [REDACTED]');
        expect(redacted).toContain('token=[REDACTED]');
        expect(redacted).toContain('password="[REDACTED]"');
        expect(redacted).not.toContain('abcdefghijkl');
        expect(redacted).not.toContain('ABCDEFGH-1234-5678-ABCD');
    });

    it('redacts additional exact secret values', () => {
        expect(redactSecrets('created local-key-value', ['local-key-value'])).toBe('created [REDACTED]');
    });

    it('keeps the commercial license generator command readable', () => {
        expect(redactSecrets('commercial-license-generator --host localhost')).toBe(
            'commercial-license-generator --host localhost',
        );
    });

    it('keeps the commercial license key file option readable', () => {
        expect(redactSecrets('--commercial-license-key-file var/admin-plugin-compatibility/dev-license.json')).toBe(
            '--commercial-license-key-file var/admin-plugin-compatibility/dev-license.json',
        );
    });

    it('keeps descriptive license generator text readable', () => {
        expect(redactSecrets('The missing license generator is classified as setup.')).toBe(
            'The missing license generator is classified as setup.',
        );
    });

    it('keeps Store shop secret error prose readable', () => {
        expect(redactSecrets('Store shop secret is invalid')).toBe('Store shop secret is invalid');
    });
});
