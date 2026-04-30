/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import fs from 'fs';
import path from 'path';
import { buildShellCommand } from './command';
import type { CliOptions, CommandRequest, CommandResult } from './types';

export const COMMERCIAL_PACKAGE_NAME = 'shopware/commercial';
export const COMMERCIAL_PLUGIN_NAME = 'SwagCommercial';
export const LICENSE_HOST_CONFIG_KEY = 'core.store.licenseHost';
export const COMMERCIAL_LICENSE_KEY_FILE_ENV = 'ADMIN_PLUGIN_COMPATIBILITY_COMMERCIAL_LICENSE_KEY_FILE';
export const BUNDLED_LICENSE_GENERATOR_HELP_MARKER = 'commercial-license-generator local compatibility wrapper';

type FileSystem = Pick<typeof fs, 'existsSync' | 'readFileSync'>;

export type CheckoutValidationResult =
    | { valid: true; composerJsonPath: string; packageName: string }
    | { valid: false; composerJsonPath: string; message: string };

export type CommercialNpmInstallState =
    | { shouldInstall: true }
    | { shouldInstall: false; reason: string };

export function resolveCommercialPath(projectRoot: string, commercialPath: string): string {
    return path.isAbsolute(commercialPath) ? commercialPath : path.join(projectRoot, commercialPath);
}

export function validateCommercialCheckout(commercialPath: string, fileSystem: FileSystem = fs): CheckoutValidationResult {
    const composerJsonPath = path.join(commercialPath, 'composer.json');

    if (!fileSystem.existsSync(composerJsonPath)) {
        return {
            valid: false,
            composerJsonPath,
            message: `Commercial composer.json was not found at ${composerJsonPath}.`,
        };
    }

    const composerJson = JSON.parse(fileSystem.readFileSync(composerJsonPath, 'utf8')) as { name?: string };

    if (composerJson.name !== COMMERCIAL_PACKAGE_NAME) {
        return {
            valid: false,
            composerJsonPath,
            message: `Expected ${COMMERCIAL_PACKAGE_NAME} at ${composerJsonPath}, got ${composerJson.name ?? 'unknown'}.`,
        };
    }

    return {
        valid: true,
        composerJsonPath,
        packageName: composerJson.name,
    };
}

export function shouldInstallCommercialNpmDependencies(commercialPath: string, fileSystem: FileSystem = fs): boolean {
    return getCommercialNpmInstallState(commercialPath, fileSystem).shouldInstall;
}

export function getCommercialNpmInstallState(commercialPath: string, fileSystem: FileSystem = fs): CommercialNpmInstallState {
    if (!fileSystem.existsSync(path.join(commercialPath, 'package-lock.json'))) {
        return {
            shouldInstall: false,
            reason: 'Commercial package-lock.json is missing.',
        };
    }

    if (fileSystem.existsSync(path.join(commercialPath, 'node_modules'))) {
        return {
            shouldInstall: false,
            reason: 'Commercial node_modules already exists.',
        };
    }

    return { shouldInstall: true };
}

export function buildLicenseGeneratorPreflightRequest(options: CliOptions, projectRoot: string): CommandRequest {
    return {
        name: 'commercial:license-generator-preflight',
        phase: 'setup',
        cwd: projectRoot,
        command: buildShellCommand(options.commercialLicenseGenerator, ['--help']),
    };
}

export function isBundledLicenseGenerator(result: CommandResult): boolean {
    return result.stdout.includes(BUNDLED_LICENSE_GENERATOR_HELP_MARKER);
}

export function resolveCommercialLicenseKeyFile(
    projectRoot: string,
    options: Pick<CliOptions, 'commercialLicenseKeyFile'>,
    env: NodeJS.ProcessEnv,
): string {
    const keyFile = options.commercialLicenseKeyFile || env[COMMERCIAL_LICENSE_KEY_FILE_ENV] || '';

    if (keyFile === '') {
        return '';
    }

    return path.isAbsolute(keyFile) ? keyFile : path.join(projectRoot, keyFile);
}

export function buildPluginRefreshRequest(options: CliOptions, projectRoot: string): CommandRequest {
    return {
        name: 'commercial:plugin-refresh',
        phase: 'setup',
        cwd: projectRoot,
        command: buildConsoleCommand(options, ['plugin:refresh']),
    };
}

export function buildPluginInstallRequest(options: CliOptions, projectRoot: string): CommandRequest {
    return {
        name: 'commercial:plugin-install',
        phase: 'setup',
        cwd: projectRoot,
        command: buildConsoleCommand(options, [
            'plugin:install',
            '--activate',
            COMMERCIAL_PLUGIN_NAME,
            '--skip-asset-build',
            '--no-interaction',
        ]),
    };
}

export function buildCommercialNpmInstallRequest(commercialPath: string): CommandRequest {
    return {
        name: 'commercial:npm-install',
        phase: 'setup',
        cwd: commercialPath,
        command: buildShellCommand('npm', [
            'ci',
            '--no-audit',
            '--prefer-offline',
        ]),
    };
}

export function buildLicenseGeneratorRequest(
    options: CliOptions,
    projectRoot: string,
    commercialLicenseKeyFile = '',
): CommandRequest {
    const args = [
        '--console',
        resolveLicenseGeneratorConsoleCommand(options),
        '--host',
        options.commercialLicenseHost,
        '--plan',
        options.commercialLicensePlan,
    ];

    if (options.forceLicense) {
        args.push('--force');
    }

    if (commercialLicenseKeyFile !== '') {
        args.push('--key-file', commercialLicenseKeyFile);
    }

    return {
        name: 'commercial:license-generator',
        phase: 'license',
        cwd: projectRoot,
        command: buildShellCommand(options.commercialLicenseGenerator, args),
    };
}

export function resolveLicenseGeneratorConsoleCommand(options: CliOptions): string {
    const generatorDockerCommand = parseDockerComposeExecCommand(options.commercialLicenseGenerator);
    const consoleDockerCommand = parseDockerComposeExecCommand(options.commercialConsoleCommand);

    if (generatorDockerCommand && consoleDockerCommand && generatorDockerCommand.service === consoleDockerCommand.service) {
        return consoleDockerCommand.innerCommand;
    }

    return options.commercialConsoleCommand;
}

export function buildCacheClearRequest(options: CliOptions, projectRoot: string): CommandRequest {
    return {
        name: 'commercial:cache-clear',
        phase: 'license',
        cwd: projectRoot,
        command: buildConsoleCommand(options, ['cache:clear']),
    };
}

export function buildLicenseHostValidationRequest(options: CliOptions, projectRoot: string): CommandRequest {
    return {
        name: 'commercial:license-host',
        phase: 'license',
        cwd: projectRoot,
        command: buildConsoleCommand(options, [
            'system:config:get',
            LICENSE_HOST_CONFIG_KEY,
            '--no-interaction',
        ]),
    };
}

export function licenseHostValidationPassed(result: CommandResult, expectedHost: string): boolean {
    return result.exitCode === 0 && result.stdout.includes(expectedHost);
}

function buildConsoleCommand(options: CliOptions, args: string[]): string {
    return buildShellCommand(options.commercialConsoleCommand, args);
}

function parseDockerComposeExecCommand(command: string): { service: string; innerCommand: string } | undefined {
    const parts = command.trim().split(/\s+/);

    if (parts[0] !== 'docker' || parts[1] !== 'compose' || parts[2] !== 'exec') {
        return undefined;
    }

    const service = parts[3];
    const innerCommand = parts.slice(4).join(' ');

    if (!service || !innerCommand) {
        return undefined;
    }

    return { service, innerCommand };
}
