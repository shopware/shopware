/**
 * @sw-package framework
 *
 * Shared fixture factories for the report spec suites. picocolors disables
 * color on non-TTY stdout (jest), so assertions run against plain text.
 */

import { renderCheckReport } from '../report';
import type { CheckExtensionsResult, ExtensionCheckResult, ToolRunResult } from '../check';
import type { SetupExtensionToolingResult } from '../setup';
import type { ExtensionToolingProject, ModeResolution } from '../shared';

export function resolution(mode: ModeResolution['mode'], overrides: Partial<ModeResolution> = {}): ModeResolution {
    return { mode, verified: true, ...overrides };
}

export function project(name: string, overrides: Partial<ExtensionToolingProject> = {}): ExtensionToolingProject {
    return {
        name,
        technicalNames: [name],
        basePath: `custom/plugins/${name}`,
        sourcePaths: [],
        vendor: false,
        bridgePresent: false,
        tsconfig: null,
        eslintConfig: null,
        ts: resolution('managed'),
        eslint: resolution('managed'),
        checkTsconfig: '',
        specTsconfig: '',
        ...overrides,
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
        tsResolution: overrides.tsResolution ?? project_.ts,
        eslintResolution: overrides.eslintResolution ?? project_.eslint,
        typescript: overrides.typescript ?? run('passed'),
        eslint: overrides.eslint ?? run('passed'),
        commands: overrides.commands ?? {},
    };
}

export function report(
    results: ExtensionCheckResult[],
    overrides: Partial<CheckExtensionsResult> = {},
    verbose = false,
): string {
    return renderCheckReport(
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
            version: 2,
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
