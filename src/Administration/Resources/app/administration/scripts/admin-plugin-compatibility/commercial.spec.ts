/**
 * @sw-package framework
 */

import fs from 'fs';
import os from 'os';
import path from 'path';
import { DEFAULTS } from './constants';
import {
    COMMERCIAL_PACKAGE_NAME,
    buildLicenseGeneratorRequest,
    buildPluginInstallRequest,
    buildLicenseGeneratorPreflightRequest,
    getCommercialNpmInstallState,
    isBundledLicenseGenerator,
    resolveCommercialLicenseKeyFile,
    resolveLicenseGeneratorConsoleCommand,
    shouldInstallCommercialNpmDependencies,
    validateCommercialCheckout,
} from './commercial';
import type { CliOptions } from './types';

describe('Commercial compatibility setup', () => {
    let temporaryDirectory = '';

    beforeEach(() => {
        temporaryDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'admin-plugin-compatibility-'));
    });

    afterEach(() => {
        fs.rmSync(temporaryDirectory, { recursive: true, force: true });
    });

    it('validates a Commercial checkout by package name', () => {
        const commercialPath = createCommercialCheckout();

        expect(validateCommercialCheckout(commercialPath)).toEqual({
            valid: true,
            composerJsonPath: path.join(commercialPath, 'composer.json'),
            packageName: COMMERCIAL_PACKAGE_NAME,
        });
    });

    it('rejects a missing Commercial checkout', () => {
        const commercialPath = path.join(temporaryDirectory, 'missing');
        const result = validateCommercialCheckout(commercialPath);

        expect(result.valid).toBe(false);
        expect(result).toEqual(expect.objectContaining({
            composerJsonPath: path.join(commercialPath, 'composer.json'),
        }));
    });

    it('detects when Commercial npm dependencies need installation', () => {
        const commercialPath = createCommercialCheckout();
        fs.writeFileSync(path.join(commercialPath, 'package-lock.json'), '{}');

        expect(shouldInstallCommercialNpmDependencies(commercialPath)).toBe(true);

        fs.mkdirSync(path.join(commercialPath, 'node_modules'));

        expect(shouldInstallCommercialNpmDependencies(commercialPath)).toBe(false);
    });

    it('reports precise Commercial npm skip reasons', () => {
        const commercialPath = createCommercialCheckout();

        expect(getCommercialNpmInstallState(commercialPath)).toEqual({
            shouldInstall: false,
            reason: 'Commercial package-lock.json is missing.',
        });

        fs.writeFileSync(path.join(commercialPath, 'package-lock.json'), '{}');
        fs.mkdirSync(path.join(commercialPath, 'node_modules'));

        expect(getCommercialNpmInstallState(commercialPath)).toEqual({
            shouldInstall: false,
            reason: 'Commercial node_modules already exists.',
        });
    });

    it('builds a license generator preflight command', () => {
        expect(buildLicenseGeneratorPreflightRequest(createOptions(), temporaryDirectory)).toEqual({
            name: 'commercial:license-generator-preflight',
            phase: 'setup',
            cwd: temporaryDirectory,
            command: 'commercial-license-generator --help',
        });
    });

    it('builds the license generator command without debug output', () => {
        const request = buildLicenseGeneratorRequest(createOptions(), temporaryDirectory);

        expect(request.command).toBe(
            'commercial-license-generator --console bin/console --host localhost --plan beyond',
        );
        expect(request.command).not.toContain('--debug');
    });

    it('passes a dev license key file only when requested by the runner', () => {
        const request = buildLicenseGeneratorRequest(
            createOptions(),
            temporaryDirectory,
            path.join(temporaryDirectory, 'dev-license.json'),
        );

        expect(request.command).toBe(
            `commercial-license-generator --console bin/console --host localhost --plan beyond --key-file ${temporaryDirectory}/dev-license.json`,
        );
    });

    it('detects the bundled fallback from the help output', () => {
        expect(isBundledLicenseGenerator(createCommandResult('commercial-license-generator local compatibility wrapper'))).toBe(true);
        expect(isBundledLicenseGenerator(createCommandResult('internal commercial-license-generator'))).toBe(false);
    });

    it('resolves explicit and environment dev license key files', () => {
        expect(resolveCommercialLicenseKeyFile(temporaryDirectory, createOptions({
            commercialLicenseKeyFile: 'var/dev-license.json',
        }), {})).toBe(path.join(temporaryDirectory, 'var/dev-license.json'));

        expect(resolveCommercialLicenseKeyFile(temporaryDirectory, createOptions(), {
            ADMIN_PLUGIN_COMPATIBILITY_COMMERCIAL_LICENSE_KEY_FILE: '/tmp/dev-license.json',
        })).toBe('/tmp/dev-license.json');
    });

    it('passes Docker console commands as one license generator argument', () => {
        const request = buildLicenseGeneratorRequest(createOptions({
            commercialConsoleCommand: 'docker compose exec web bin/console',
            forceLicense: true,
        }), temporaryDirectory);

        expect(request.command).toBe(
            "commercial-license-generator --console 'docker compose exec web bin/console' --host localhost --plan beyond --force",
        );
    });

    it('uses the container-local console when generator and console commands target the same compose service', () => {
        const options = createOptions({
            commercialLicenseGenerator: 'docker compose exec web commercial-license-generator',
            commercialConsoleCommand: 'docker compose exec web bin/console',
        });
        const request = buildLicenseGeneratorRequest(options, temporaryDirectory);

        expect(resolveLicenseGeneratorConsoleCommand(options)).toBe('bin/console');
        expect(request.command).toBe(
            'docker compose exec web commercial-license-generator --console bin/console --host localhost --plan beyond',
        );
    });

    it('keeps Docker console commands executable for plugin installation', () => {
        const request = buildPluginInstallRequest(createOptions({
            commercialConsoleCommand: 'docker compose exec web bin/console',
        }), temporaryDirectory);

        expect(request.command).toBe(
            'docker compose exec web bin/console plugin:install --activate SwagCommercial --skip-asset-build --no-interaction',
        );
    });

    function createCommercialCheckout(): string {
        const commercialPath = path.join(temporaryDirectory, 'custom/plugins/SwagCommercial');

        fs.mkdirSync(commercialPath, { recursive: true });
        fs.writeFileSync(path.join(commercialPath, 'composer.json'), JSON.stringify({ name: COMMERCIAL_PACKAGE_NAME }));

        return commercialPath;
    }
});

function createOptions(overrides: Partial<CliOptions> = {}): CliOptions {
    return {
        ...DEFAULTS,
        components: [...DEFAULTS.components],
        ...overrides,
    };
}

function createCommandResult(stdout: string): Parameters<typeof isBundledLicenseGenerator>[0] {
    return {
        name: 'commercial:license-generator-preflight',
        phase: 'setup',
        cwd: '/project',
        command: 'commercial-license-generator --help',
        stdout,
        stderr: '',
        exitCode: 0,
        durationMs: 1,
        startedAt: '2026-04-30T00:00:00.000Z',
    };
}
