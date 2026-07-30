/**
 * @sw-package framework
 *
 * Shared fixture factories for the report spec suites. picocolors enables
 * color whenever env.CI is set (GitHub Actions), so the render wrapper here
 * strips ANSI sequences before semantic assertions; color.spec.ts covers the
 * colored rendering.
 */

import path from 'path';
import { renderSetupReport } from '../report';
import type { SetupExtensionToolingResult } from '../setup';
import type { AdministrationTarget, ExtensionToolingProject, OwnedConfig } from '../shared';

// picocolors only ever emits SGR sequences (ESC[…m), so this narrow pattern
// is exact for the renderer's output.
// eslint-disable-next-line no-control-regex
const ANSI_SGR_PATTERN = /\x1b\[[0-9;]*m/g;

export function stripAnsi(value: string): string {
    return value.replace(ANSI_SGR_PATTERN, '');
}

/** An extension-owned config at `path`; a `detail` implies it does not compose. */
export function owned(configPath: string, detail?: string): OwnedConfig {
    return detail === undefined ? { path: configPath, composes: true } : { path: configPath, composes: false, detail };
}

type ProjectOverrides = Partial<ExtensionToolingProject> & Partial<AdministrationTarget>;

export function project(name: string, overrides: ProjectOverrides = {}): ExtensionToolingProject {
    const sourcePath = overrides.sourcePath ?? `custom/plugins/${name}/src`;

    return {
        name: overrides.name ?? name,
        technicalNames: overrides.technicalNames ?? [name],
        basePath: overrides.basePath ?? `custom/plugins/${name}`,
        vendor: overrides.vendor ?? false,
        targets: overrides.targets ?? [
            {
                technicalNames: overrides.technicalNames ?? [name],
                sourcePath,
                adminFolder: overrides.adminFolder ?? path.posix.dirname(sourcePath),
                bridgePresent: overrides.bridgePresent ?? false,
                tsconfig: overrides.tsconfig ?? null,
                eslintConfig: overrides.eslintConfig ?? null,
            },
        ],
    };
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
