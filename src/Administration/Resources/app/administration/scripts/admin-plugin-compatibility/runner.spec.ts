/**
 * @sw-package framework
 */

import fs from 'fs';
import os from 'os';
import path from 'path';
import { DEFAULTS, EXIT_CODES } from './constants';
import { BUNDLED_LICENSE_GENERATOR_HELP_MARKER, COMMERCIAL_PACKAGE_NAME } from './commercial';
import { runCompatibilityWorkflow } from './runner';
import type { CliOptions, CommandRequest, CommandResult } from './types';

describe('admin-plugin-compatibility workflow runner', () => {
    let projectRoot = '';

    beforeEach(() => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'admin-plugin-compatibility-'));
    });

    afterEach(() => {
        fs.rmSync(projectRoot, { recursive: true, force: true });
    });

    it('runs Commercial setup, license, and build commands in order', async () => {
        createCommercialCheckout({ packageLock: true });
        const requests: CommandRequest[] = [];

        const result = await runCompatibilityWorkflow(createOptions(), {
            env: {},
            projectRoot,
            runCommand: (request) => {
                requests.push(request);

                return Promise.resolve(
                    createCommandResult(request, request.name === 'commercial:license-host' ? 'localhost\n' : 'ok\n'),
                );
            },
        });

        expect(result.status).toBe('passed');
        expect(result.exitCode).toBe(EXIT_CODES.success);
        expect(requests.map((request) => request.name)).toEqual([
            'commercial:license-generator-preflight',
            'commercial:plugin-refresh',
            'commercial:plugin-install',
            'commercial:npm-install',
            'commercial:license-generator',
            'commercial:cache-clear',
            'commercial:license-host',
            'admin:build',
            'admin:plugin-compatibility-smoke',
        ]);
    });

    it('skips the Admin build when requested', async () => {
        createCommercialCheckout({ nodeModules: true });

        const result = await runCompatibilityWorkflow(createOptions({ skipBuild: true }), {
            env: {},
            projectRoot,
            runCommand: (request) => Promise.resolve(
                createCommandResult(request, request.name === 'commercial:license-host' ? 'localhost\n' : 'ok\n'),
            ),
        });

        expect(result.status).toBe('passed');
        expect(result.commands.map((command) => command.name)).not.toContain('admin:build');
        expect(result.commands.map((command) => command.name)).toContain('admin:plugin-compatibility-smoke');
        expect(result.steps.find((step) => step.name === 'admin:build')).toEqual(expect.objectContaining({
            status: 'skipped',
        }));
    });

    it('fails before plugin mutations when the license generator is missing', async () => {
        createCommercialCheckout({ nodeModules: true });
        const requests: CommandRequest[] = [];

        const result = await runCompatibilityWorkflow(createOptions(), {
            env: {},
            projectRoot,
            runCommand: (request) => {
                requests.push(request);

                return Promise.resolve(createCommandResult(
                    request,
                    '',
                    request.name === 'commercial:license-generator-preflight' ? 127 : 0,
                    request.name === 'commercial:license-generator-preflight' ? 'commercial-license-generator: command not found' : '',
                ));
            },
        });

        expect(result.status).toBe('failed');
        expect(result.failureClass).toBe('setup');
        expect(result.exitCode).toBe(EXIT_CODES.setup);
        expect(requests.map((request) => request.name)).toEqual(['commercial:license-generator-preflight']);
        expect(result.steps.find((step) => step.name === 'commercial:license-generator-preflight')).toEqual(expect.objectContaining({
            status: 'failed',
            message: 'License generator command was not found: commercial-license-generator',
        }));
    });

    it('fails before plugin mutations when the bundled fallback has no dev license key file', async () => {
        createCommercialCheckout({ nodeModules: true });
        const requests: CommandRequest[] = [];

        const result = await runCompatibilityWorkflow(createOptions(), {
            env: {},
            projectRoot,
            runCommand: (request) => {
                requests.push(request);

                return Promise.resolve(createCommandResult(request, BUNDLED_LICENSE_GENERATOR_HELP_MARKER));
            },
        });

        expect(result.status).toBe('failed');
        expect(result.failureClass).toBe('setup');
        expect(result.exitCode).toBe(EXIT_CODES.setup);
        expect(requests.map((request) => request.name)).toEqual(['commercial:license-generator-preflight']);
        expect(result.steps.find((step) => step.name === 'commercial:license-generator-preflight')).toEqual(expect.objectContaining({
            status: 'failed',
            message: 'Bundled commercial-license-generator fallback requires --commercial-license-key-file or ADMIN_PLUGIN_COMPATIBILITY_COMMERCIAL_LICENSE_KEY_FILE before plugin mutations.',
        }));
    });

    it('passes the dev license key file to the bundled fallback', async () => {
        createCommercialCheckout({ nodeModules: true });
        fs.mkdirSync(path.join(projectRoot, 'var/admin-plugin-compatibility'), { recursive: true });
        fs.writeFileSync(path.join(projectRoot, 'var/admin-plugin-compatibility/dev-license.json'), '{"key":"license"}');

        const result = await runCompatibilityWorkflow(createOptions({
            commercialLicenseKeyFile: 'var/admin-plugin-compatibility/dev-license.json',
            skipBuild: true,
        }), {
            env: {},
            projectRoot,
            runCommand: (request) => Promise.resolve(createCommandResult(
                request,
                request.name === 'commercial:license-generator-preflight'
                    ? BUNDLED_LICENSE_GENERATOR_HELP_MARKER
                    : request.name === 'commercial:license-host' ? 'localhost\n' : 'ok\n',
            )),
        });

        const licenseCommand = result.commands.find((command) => command.name === 'commercial:license-generator');

        expect(result.status).toBe('passed');
        expect(licenseCommand?.command).toContain(
            `--key-file ${projectRoot}/var/admin-plugin-compatibility/dev-license.json`,
        );
    });

    it('fails setup before running commands when Commercial is missing', async () => {
        const result = await runCompatibilityWorkflow(createOptions(), {
            env: {},
            projectRoot,
            runCommand: (request) => Promise.resolve(createCommandResult(request)),
        });

        expect(result.status).toBe('failed');
        expect(result.failureClass).toBe('setup');
        expect(result.exitCode).toBe(EXIT_CODES.setup);
        expect(result.commands).toEqual([]);
    });

    it('fails licensing when the license host is not configured', async () => {
        createCommercialCheckout({ nodeModules: true });

        const result = await runCompatibilityWorkflow(createOptions(), {
            env: {},
            projectRoot,
            runCommand: (request) => Promise.resolve(
                createCommandResult(
                    request,
                    request.name === 'commercial:license-host' ? 'shopware.test\n' : 'ok\n',
                ),
            ),
        });

        expect(result.status).toBe('failed');
        expect(result.failureClass).toBe('license');
        expect(result.exitCode).toBe(EXIT_CODES.license);
        expect(result.steps.find((step) => step.name === 'commercial:license-host')).toEqual(expect.objectContaining({
            status: 'failed',
        }));
    });

    it('fails with build classification when the Admin build fails', async () => {
        createCommercialCheckout({ nodeModules: true });

        const result = await runCompatibilityWorkflow(createOptions(), {
            env: {},
            projectRoot,
            runCommand: (request) => Promise.resolve(createCommandResult(
                request,
                request.name === 'commercial:license-host' ? 'localhost\n' : 'ok\n',
                request.name === 'admin:build' ? 1 : 0,
            )),
        });

        expect(result.status).toBe('failed');
        expect(result.failureClass).toBe('build');
        expect(result.exitCode).toBe(EXIT_CODES.build);
    });

    it('fails with runtime classification when the smoke suite fails', async () => {
        createCommercialCheckout({ nodeModules: true });

        const result = await runCompatibilityWorkflow(createOptions(), {
            env: {},
            projectRoot,
            runCommand: (request) => Promise.resolve(createCommandResult(
                request,
                request.name === 'commercial:license-host' ? 'localhost\n' : 'ok\n',
                request.name === 'admin:plugin-compatibility-smoke' ? 1 : 0,
            )),
        });

        expect(result.status).toBe('failed');
        expect(result.failureClass).toBe('runtime');
        expect(result.exitCode).toBe(EXIT_CODES.runtime);
    });

    it('filters smoke execution to requested component smoke cases', async () => {
        createCommercialCheckout({ nodeModules: true });

        const result = await runCompatibilityWorkflow(createOptions({
            components: [
                'sw-media-library',
                'sw-unknown-component',
            ],
        }), {
            env: {},
            projectRoot,
            runCommand: (request) => Promise.resolve(
                createCommandResult(request, request.name === 'commercial:license-host' ? 'localhost\n' : 'ok\n'),
            ),
        });

        const smokeCommand = result.commands.find((command) => command.name === 'admin:plugin-compatibility-smoke');

        expect(result.coverageGaps).toEqual(['sw-unknown-component']);
        expect(smokeCommand?.command).toContain('--grep @compatibility-sw-media-library');
        expect(smokeCommand?.command).not.toContain('@compatibility-sw-settings-search');
    });

    function createCommercialCheckout(options: { packageLock?: boolean; nodeModules?: boolean } = {}): string {
        const commercialPath = path.join(projectRoot, DEFAULTS.commercialPath);

        fs.mkdirSync(commercialPath, { recursive: true });
        fs.writeFileSync(path.join(commercialPath, 'composer.json'), JSON.stringify({ name: COMMERCIAL_PACKAGE_NAME }));

        if (options.packageLock) {
            fs.writeFileSync(path.join(commercialPath, 'package-lock.json'), '{}');
        }

        if (options.nodeModules) {
            fs.mkdirSync(path.join(commercialPath, 'node_modules'));
        }

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

function createCommandResult(request: CommandRequest, stdout = 'ok\n', exitCode = 0, stderr = ''): CommandResult {
    return {
        ...request,
        stdout,
        stderr,
        exitCode,
        startedAt: '2026-04-30T00:00:00.000Z',
        durationMs: 1,
    };
}
