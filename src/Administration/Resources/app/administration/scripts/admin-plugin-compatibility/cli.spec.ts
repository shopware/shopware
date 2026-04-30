/**
 * @sw-package framework
 */

import { DEFAULTS } from './constants';
import { parseCliArguments } from './cli';

describe('admin-plugin-compatibility CLI parsing', () => {
    it('parses documented defaults', () => {
        const parsed = parseCliArguments([]);

        expect(parsed).toEqual({
            type: 'options',
            options: DEFAULTS,
        });
    });

    it('parses explicit option values', () => {
        const parsed = parseCliArguments([
            '--profile',
            'commercial',
            '--commercial-path=custom/plugins/CommercialOverride',
            '--commercial-license-generator',
            'vendor/bin/commercial-license-generator',
            '--commercial-license-key-file',
            'var/admin-plugin-compatibility/dev-license.json',
            '--commercial-license-host',
            'shopware.test',
            '--commercial-license-plan',
            'enterprise',
            '--commercial-console-command',
            'docker compose exec web bin/console',
            '--force-license',
            '--components',
            'sw-media-library,sw-settings-search',
            '--components',
            'sw-product-list',
            '--report-dir',
            'var/custom-reports',
            '--baseline-file',
            'var/custom-baseline.json',
            '--skip-build',
            '--write-baseline',
        ]);

        expect(parsed).toEqual({
            type: 'options',
            options: {
                profile: 'commercial',
                commercialPath: 'custom/plugins/CommercialOverride',
                commercialLicenseGenerator: 'vendor/bin/commercial-license-generator',
                commercialLicenseKeyFile: 'var/admin-plugin-compatibility/dev-license.json',
                commercialLicenseHost: 'shopware.test',
                commercialLicensePlan: 'enterprise',
                commercialConsoleCommand: 'docker compose exec web bin/console',
                forceLicense: true,
                components: [
                    'sw-media-library',
                    'sw-settings-search',
                    'sw-product-list',
                ],
                reportDir: 'var/custom-reports',
                baselineFile: 'var/custom-baseline.json',
                skipBuild: true,
                writeBaseline: true,
            },
        });
    });

    it('rejects unsupported profiles for the Commercial-only phase', () => {
        const parsed = parseCliArguments([
            '--profile',
            'representative',
        ]);

        expect(parsed.type).toBe('error');
    });

    it('returns help without validating other arguments', () => {
        const parsed = parseCliArguments([
            '--help',
        ]);

        expect(parsed.type).toBe('help');
    });
});
