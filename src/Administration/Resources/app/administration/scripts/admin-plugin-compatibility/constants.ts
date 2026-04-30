/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import path from 'path';

export const CI_GUARD_ENV_VARS = [
    'CI',
    'GITHUB_ACTIONS',
    'GITLAB_CI',
] as const;

export const EXIT_CODES = {
    success: 0,
    usage: 2,
    ci: 3,
    setup: 10,
    license: 20,
    build: 30,
    runtime: 40,
} as const;

export const DEFAULT_COMMERCIAL_TOGGLE = 'TEXT_TO_IMAGE_GENERATION-6841914';

export const DEFAULTS = {
    profile: 'commercial',
    commercialPath: 'custom/plugins/SwagCommercial',
    commercialLicenseGenerator: 'commercial-license-generator',
    commercialLicenseKeyFile: '',
    commercialLicenseHost: 'localhost',
    commercialLicensePlan: 'beyond',
    commercialConsoleCommand: 'bin/console',
    forceLicense: false,
    components: [] as string[],
    reportDir: 'var/admin-plugin-compatibility/reports/',
    baselineFile: 'var/admin-plugin-compatibility/baseline/commercial.json',
    skipBuild: false,
    writeBaseline: false,
} as const;

export function resolveProjectRoot(): string {
    return path.resolve(process.env.PROJECT_ROOT ?? path.join(__dirname, '../../../../../../..'));
}
