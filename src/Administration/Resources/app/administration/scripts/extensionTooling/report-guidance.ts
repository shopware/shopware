/**
 * @sw-package framework
 *
 * Remediation text for the setup report: per-tool "why/fix"
 * guidance, an extension's single missing setup step, and the file-ownership
 * classification that dry-run output relies on. Pure string logic — no color —
 * so both report renderers can share it without pulling in picocolors.
 */

import {
    BRIDGE_ESLINT_SPECIFIER,
    BRIDGE_TSCONFIG_EXTENDS,
    DEFAULT_TOOLING_COMMANDS,
    SHIM_DIR_NAME,
    aggregateModeResolution,
    deriveExtensionState,
} from './shared';
import type { ExtensionToolingProject, ModeResolution, ToolingCommands } from './shared';

/**
 * Ownership class of a generated path, so dry-run and bridge output can tell
 * apart the lifecycles a developer must treat differently:
 * - `bridge`: git-ignored machine-specific files inside `.shopware/` —
 *   never committed;
 * - `committable`: the plugin's own tsconfig/eslint — commit these;
 * - `vendor-config`: the same scaffolds inside a composer-managed extension —
 *   local only, restored by re-running setup after a composer update;
 * - `host`: disposable root projections and IDE bootstraps the tool re-derives.
 */
export type FileClass = 'bridge' | 'committable' | 'vendor-config' | 'host';

export function classifyFile(file: string): FileClass {
    if (file.includes(`/${SHIM_DIR_NAME}/`)) {
        return 'bridge';
    }

    const base = file.split('/').pop() ?? '';

    if (base === 'tsconfig.json' || base === 'eslint.config.mjs') {
        if (file.startsWith('custom/plugins/')) {
            return 'committable';
        }

        if (file.startsWith('vendor/')) {
            return 'vendor-config';
        }
    }

    return 'host';
}

export interface ToolGuidance {
    why: string;
    fix: string[];
}

const BRIDGE_TSCONFIG_LINE = `"extends": "${BRIDGE_TSCONFIG_EXTENDS}"`;
const BRIDGE_ESLINT_LINES = [
    `import shopware from '${BRIDGE_ESLINT_SPECIFIER}';`,
    'export default [ ...shopware /* , your rules */ ];',
];

/**
 * One `why:` and one `fix:` for a skipped tool — reason- and state-specific,
 * never suggesting a step the project's facts prove was already done.
 */
export function describeToolGuidance(
    project: ExtensionToolingProject,
    tool: 'TypeScript' | 'ESLint',
    resolution: ModeResolution,
): ToolGuidance | null {
    if (resolution.mode !== 'unmanaged') {
        return null;
    }

    const state = deriveExtensionState(project);

    if (state === 'platform') {
        // Platform bundles are checked by the core toolchain; per-tool fixes
        // would point at files this tool never manages.
        return null;
    }

    const why = resolution.detail ?? 'the config does not compose the Shopware preset.';

    if (state === 'needs-bridge') {
        // The per-extension one-command block covers the fix; repeating it per
        // tool would print the same command twice.
        return { why, fix: [] };
    }

    if (resolution.reason === 'config-error') {
        return { why, fix: ['fix the config error, then re-run setup.'] };
    }

    if (tool === 'TypeScript') {
        switch (resolution.reason) {
            case 'files-override':
                return {
                    why,
                    fix: ['remove "files" from the plugin tsconfig — the bridge provides the type surface.'],
                };
            case 'not-extending':
                return { why, fix: [`add ${BRIDGE_TSCONFIG_LINE} to the plugin tsconfig.`] };
            default:
                return {
                    why,
                    fix: [`extend the bridge (${BRIDGE_TSCONFIG_LINE}) and remove own "files" / "types" overrides.`],
                };
        }
    }

    return {
        why,
        fix: [
            'compose the bridge in the config:',
            ...BRIDGE_ESLINT_LINES.map((line) => `    ${line}`),
        ],
    };
}

/** The note appended for vendor extensions whose files this run touched or should touch. */
function vendorLifecycleNote(): string[] {
    return [
        '(Vendor extension — files written under vendor/ are local only; a composer',
        'update removes them and re-running setup restores them.)',
    ];
}

/**
 * The extension's single missing step for the setup report — empty when
 * nothing is missing.
 */
export function describeNextStep(
    project: ExtensionToolingProject,
    commands: ToolingCommands = DEFAULT_TOOLING_COMMANDS,
): string[] {
    const state = deriveExtensionState(project);

    if (state === 'needs-bridge') {
        return [
            `It isn't bridged yet. Bridges are generated automatically — run \`${commands.setup}\`;`,
            'if a warning above explains why the bridge was skipped, fix that first.',
            ...(project.vendor ? vendorLifecycleNote() : []),
        ];
    }

    if (state === 'bridge-unwired') {
        const lines = [`The ${SHIM_DIR_NAME}/ bridge exists — finish wiring it:`];

        for (const [
            tool,
            resolution,
        ] of [
            [
                'TypeScript',
                aggregateModeResolution(project, 'ts'),
            ],
            [
                'ESLint',
                aggregateModeResolution(project, 'eslint'),
            ],
        ] as Array<['TypeScript' | 'ESLint', ModeResolution]>) {
            const guidance = describeToolGuidance(project, tool, resolution);

            if (guidance) {
                lines.push(...guidance.fix.map((line) => `    ${line}`));
            }
        }

        if (project.vendor) {
            lines.push(...vendorLifecycleNote());
        }

        return lines;
    }

    return [];
}
