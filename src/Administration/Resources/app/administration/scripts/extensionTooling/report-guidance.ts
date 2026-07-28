/**
 * @sw-package framework
 *
 * Remediation text for the check and setup reports: per-tool "why/fix"
 * guidance, an extension's single missing setup step, and the file-ownership
 * classification that dry-run output relies on. Pure string logic — no color —
 * so both report renderers can share it without pulling in picocolors.
 */

import { DEFAULT_TOOLING_COMMANDS, SHIM_DIR_NAME, aggregateModeResolution, deriveExtensionState } from './shared';
import type { ExtensionToolingProject, ModeResolution, ToolingCommands } from './shared';

/**
 * Ownership class of a generated path, so dry-run and shim output can tell
 * apart the three lifecycles a developer must treat differently:
 * - `bridge`: git-ignored machine-specific files inside `.shopware-admin/` —
 *   never committed;
 * - `committable`: the plugin's own tsconfig/eslint/baseline — commit these;
 * - `host`: disposable root projections and IDE bootstraps the tool re-derives.
 */
export type FileClass = 'bridge' | 'committable' | 'host';

export function classifyFile(file: string): FileClass {
    if (file.includes(`/${SHIM_DIR_NAME}/`)) {
        return 'bridge';
    }

    const base = file.split('/').pop() ?? '';

    if (
        file.startsWith('custom/plugins/') &&
        (base === 'tsconfig.json' || base === 'eslint.config.mjs' || base === '.shopware-admin-baseline.json')
    ) {
        return 'committable';
    }

    return 'host';
}

export interface ToolGuidance {
    why: string;
    fix: string[];
}

const BRIDGE_TSCONFIG_LINE = '"extends": "./.shopware-admin/tsconfig.json"';
const BRIDGE_ESLINT_LINES = [
    "import shopware from './.shopware-admin/eslint.mjs';",
    'export default [ ...shopware /* , your rules */ ];',
];

function shimCommand(project: ExtensionToolingProject, commands: ToolingCommands): string {
    return `${commands.setup} -- --shim=${project.name}`;
}

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

    if (state === 'vendor' || state === 'platform') {
        // Rendered as a per-extension note instead of per-tool fixes.
        return null;
    }

    const why = resolution.detail ?? 'the config does not compose the Shopware preset.';

    if (state === 'needs-bridge') {
        // The per-extension one-command block covers the fix; repeating it per
        // tool would print the same command twice.
        return { why, fix: [] };
    }

    if (resolution.reason === 'config-error') {
        return { why, fix: ['fix the config error, then re-run the check.'] };
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

/**
 * The extension's single missing step for the setup report — empty when
 * nothing is missing.
 */
export function describeNextStep(
    project: ExtensionToolingProject,
    commands: ToolingCommands = DEFAULT_TOOLING_COMMANDS,
): string[] {
    const state = deriveExtensionState(project);

    if (state === 'vendor') {
        return [
            'Read-only vendor extension — checked through host-owned configs; findings are',
            'non-fatal (pass --strict-vendor to fail on them).',
        ];
    }

    if (state === 'needs-bridge') {
        return [
            "It isn't checked with the Shopware preset yet. Bridge it with one command:",
            `    ${shimCommand(project, commands)}`,
            'That generates a git-ignored .shopware-admin/ bridge plus small committed',
            'tsconfig/eslint that extend it (existing configs are never overwritten).',
        ];
    }

    if (state === 'bridge-unwired') {
        const lines = ['The .shopware-admin/ bridge exists — finish wiring it:'];

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

        return lines;
    }

    return [];
}
