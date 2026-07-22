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
import type { CheckExtensionsResult, ExtensionCheckResult, ToolRunResult } from '../check';
import type { SetupExtensionToolingResult } from '../setup';
import { aggregateModeResolution } from '../shared';
import type { AdministrationTarget, ExtensionToolingProject, ModeResolution } from '../shared';

// picocolors only ever emits SGR sequences (ESC[…m), so this narrow pattern
// is exact for the renderer's output.
// eslint-disable-next-line no-control-regex
const ANSI_SGR_PATTERN = /\x1b\[[0-9;]*m/g;

export function stripAnsi(value: string): string {
    return value.replace(ANSI_SGR_PATTERN, '');
}

export function resolution(mode: ModeResolution['mode'], overrides: Partial<ModeResolution> = {}): ModeResolution {
    return { mode, verified: true, ...overrides };
}

type ProjectOverrides = Partial<ExtensionToolingProject> & Partial<AdministrationTarget> & { sourcePaths?: string[] };

export function project(name: string, overrides: ProjectOverrides = {}): ExtensionToolingProject {
    const sourcePath = overrides.sourcePath ?? overrides.sourcePaths?.[0] ?? `custom/plugins/${name}/src`;
    const target: AdministrationTarget = {
        technicalNames: overrides.technicalNames ?? [name],
        sourcePath,
        adminFolder: overrides.adminFolder ?? path.posix.dirname(sourcePath),
        bridgePresent: overrides.bridgePresent ?? false,
        tsconfig: overrides.tsconfig ?? null,
        eslintConfig: overrides.eslintConfig ?? null,
        ts: overrides.ts ?? resolution('managed'),
        eslint: overrides.eslint ?? resolution('managed'),
        checkTsconfig: overrides.checkTsconfig ?? '',
        specTsconfig: overrides.specTsconfig ?? '',
    };

    return {
        name: overrides.name ?? name,
        technicalNames: overrides.technicalNames ?? [name],
        basePath: overrides.basePath ?? `custom/plugins/${name}`,
        vendor: overrides.vendor ?? false,
        targets: overrides.targets ?? [target],
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
        tsResolution: overrides.tsResolution ?? aggregateModeResolution(project_, 'ts'),
        eslintResolution: overrides.eslintResolution ?? aggregateModeResolution(project_, 'eslint'),
        typescript: overrides.typescript ?? run('passed'),
        typescriptSpecs: overrides.typescriptSpecs ?? run('no-files'),
        eslint: overrides.eslint ?? run('passed'),
        commands: overrides.commands ?? {},
        coverage: overrides.coverage ?? [],
    };
}

export function checkReport(...args: Parameters<typeof renderCheckReport>): string {
    return stripAnsi(renderCheckReport(...args));
}

export function setupReport(...args: Parameters<typeof renderSetupReport>): string {
    return stripAnsi(renderSetupReport(...args));
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
        discoverySource: { path: 'var/plugins.json', updatedAt: null },
        ...overrides,
    };
}
