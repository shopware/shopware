/**
 * @sw-package framework
 *
 * Shared fixture factories for the report spec suites. picocolors enables
 * color whenever env.CI is set (GitHub Actions), so the render wrappers here
 * strip ANSI sequences before semantic assertions; color.spec.ts covers the
 * colored rendering.
 */

import path from 'path';
import { renderCheckReport, renderSetupReport } from '../report';
import { collectSkippedTargets } from '../check-types';
import type { CheckExtensionsResult, ExtensionCheckResult, ToolRunResult } from '../check-types';
import type { SetupExtensionToolingResult } from '../setup';
import { firstDrift } from '../shared';
import type { AdministrationTarget, ConfigDefect, ExtensionToolingProject, OwnedConfig } from '../shared';

// picocolors only ever emits SGR sequences (ESC[…m), so this narrow pattern
// is exact for the renderer's output.
// eslint-disable-next-line no-control-regex
const ANSI_SGR_PATTERN = /\x1b\[[0-9;]*m/g;

export function stripAnsi(value: string): string {
    return value.replace(ANSI_SGR_PATTERN, '');
}

/**
 * An extension-owned config at `path`; a `detail` implies it does not compose.
 * The defect defaults to a missing `extends` — the common drift — so only specs
 * about a different trap have to name one.
 */
export function owned(configPath: string, detail?: string, reason: ConfigDefect = 'extends-missing'): OwnedConfig {
    return detail === undefined
        ? { path: configPath, composes: true }
        : { path: configPath, composes: false, detail, reason };
}

type ProjectOverrides = Partial<ExtensionToolingProject> & Partial<AdministrationTarget>;

export function target(name: string, overrides: Partial<AdministrationTarget> = {}): AdministrationTarget {
    const sourcePath = overrides.sourcePath ?? `custom/plugins/${name}/src`;

    return {
        technicalNames: overrides.technicalNames ?? [name],
        sourcePath,
        adminFolder: overrides.adminFolder ?? path.posix.dirname(sourcePath),
        bridgePresent: overrides.bridgePresent ?? false,
        tsconfig: overrides.tsconfig ?? null,
        eslintConfig: overrides.eslintConfig ?? null,
    };
}

export function project(name: string, overrides: ProjectOverrides = {}): ExtensionToolingProject {
    return {
        name: overrides.name ?? name,
        technicalNames: overrides.technicalNames ?? [name],
        basePath: overrides.basePath ?? `custom/plugins/${name}`,
        vendor: overrides.vendor ?? false,
        targets: overrides.targets ?? [target(name, overrides)],
    };
}

export function run(status: ToolRunResult['status'], overrides: Partial<ToolRunResult> = {}): ToolRunResult {
    return { status, output: '', durationMs: 1000, findings: 0, ...overrides };
}

export function extension(
    project_: ExtensionToolingProject,
    overrides: Partial<ExtensionCheckResult> = {},
): ExtensionCheckResult {
    return {
        project: project_,
        tsResolution: overrides.tsResolution ?? firstDrift(project_, 'tsconfig'),
        eslintResolution: overrides.eslintResolution ?? firstDrift(project_, 'eslintConfig'),
        typescript: overrides.typescript ?? run('passed'),
        typescriptSpecs: overrides.typescriptSpecs ?? run('no-files'),
        eslint: overrides.eslint ?? run('passed'),
        commands: overrides.commands ?? {},
        coverage: overrides.coverage ?? [],
        skippedTargets: overrides.skippedTargets ?? collectSkippedTargets(project_),
    };
}

export function checkReport(...args: Parameters<typeof renderCheckReport>): string {
    return stripAnsi(renderCheckReport(...args));
}

export function report(
    results: ExtensionCheckResult[],
    overrides: Partial<CheckExtensionsResult> = {},
    verbose = false,
): string {
    return checkReport(
        { results, fatalDiagnostics: [], warnings: [], baselineUpdates: [], exitCode: 0, ...overrides },
        { verbose },
    );
}

export function setupReport(...args: Parameters<typeof renderSetupReport>): string {
    return stripAnsi(renderSetupReport(...args));
}

export function setupResult(
    projects: ExtensionToolingProject[],
    overrides: Partial<SetupExtensionToolingResult> = {},
): SetupExtensionToolingResult {
    return {
        manifest: {
            version: 3,
            adminRoot: 'src/Administration/Resources/app/administration',
            entitySchemaAvailable: true,
            hostModules: {},
            rootConfigs: { tsconfig: 'managed', eslintConfig: 'managed' },
            ideBootstraps: {},
            gitignore: { state: 'managed', optedOut: false },
            projects,
        },
        manifestPath: 'var/admin-extension-tooling/manifest.json',
        writes: [],
        staleFiles: [],
        warnings: [],
        instructions: [],
        changed: false,
        ...overrides,
    };
}
