/**
 * @sw-package framework
 *
 * Remediation text for the check and setup reports: per-tool "why/fix"
 * guidance, an extension's single missing setup step, and the file-ownership
 * classification that dry-run output relies on. Pure string logic — no color —
 * so both report renderers can share it without pulling in picocolors.
 */

import {
    BRIDGE_ESLINT_SPECIFIER,
    BRIDGE_TSCONFIG_EXTENDS,
    DEFAULT_TOOLING_COMMANDS,
    deriveExtensionState,
    firstDrift,
    SHIM_DIR_NAME,
} from './shared';
import type { ExtensionToolingProject, OwnedConfig, ToolingCommands } from './shared';

export interface ToolGuidance {
    why: string;
    fix: string[];
}

const BRIDGE_TSCONFIG_LINE = `"extends": "${BRIDGE_TSCONFIG_EXTENDS}"`;
const BRIDGE_INCLUDE_LINE = '"include": ["src/**/*.ts", "src/**/*.vue"]';
const BRIDGE_ESLINT_LINES = [
    `import shopware from '${BRIDGE_ESLINT_SPECIFIER}';`,
    'export default [ ...shopware /* , your rules */ ];',
];

/**
 * The concrete step that fixes this config's defect. Derived from the verdict's
 * `reason` rather than from the extension's state, so a tsconfig that already
 * carries its `extends` is told to drop its `files` array instead of being sent
 * to add an `extends` it has.
 */
export function describeConfigFix(tool: 'TypeScript' | 'ESLint', config: OwnedConfig): string[] {
    if (tool === 'ESLint') {
        return [
            'compose the bridge in the eslint config:',
            ...BRIDGE_ESLINT_LINES.map((line) => `    ${line}`),
        ];
    }

    switch (config.reason) {
        case 'files-override':
            return ['remove the own "files" array from the tsconfig — the bridge provides the type surface.'];
        case 'include-missing':
            return [
                'add an "include" to the tsconfig naming your sources:',
                `    ${BRIDGE_INCLUDE_LINE}`,
            ];
        case 'unreadable':
            return ['repair the tsconfig so it parses, then re-run setup.'];
        default:
            return [`add ${BRIDGE_TSCONFIG_LINE} to the tsconfig.`];
    }
}

/**
 * One `why:` and one `fix:` for a skipped tool. The human "why" comes from the
 * config's own drift detail; the "fix" is defect-specific, never suggesting a
 * step the config's own facts prove was already done.
 */
export function describeToolGuidance(
    project: ExtensionToolingProject,
    tool: 'TypeScript' | 'ESLint',
    config: OwnedConfig | null,
): ToolGuidance | null {
    if (config === null || config.composes) {
        return null;
    }

    if (project.vendor || deriveExtensionState(project) === 'platform') {
        // Rendered as a per-extension note instead of per-tool fixes.
        return null;
    }

    const why = config.detail ?? 'the config does not compose the Shopware preset.';

    if (deriveExtensionState(project) === 'needs-bridge') {
        // The per-extension one-command block covers the fix; repeating it per
        // tool would print the same command twice.
        return { why, fix: [] };
    }

    return { why, fix: describeConfigFix(tool, config) };
}

/**
 * The extension's single missing step for the report — empty when nothing is
 * missing. Bridges are generated automatically, so the "not bridged yet" step
 * is just re-running setup.
 */
export function describeNextStep(
    project: ExtensionToolingProject,
    commands: ToolingCommands = DEFAULT_TOOLING_COMMANDS,
): string[] {
    if (project.vendor) {
        return [
            'Read-only vendor extension — checked through host-owned configs; findings are',
            'non-fatal (pass --strict-vendor to fail on them).',
        ];
    }

    const state = deriveExtensionState(project);

    if (state === 'needs-bridge') {
        return [
            "It isn't checked with the Shopware preset yet. Bridges are generated automatically —",
            `    run \`${commands.setup}\``,
            `That generates a git-ignored ${SHIM_DIR_NAME}/ bridge plus small committed`,
            'tsconfig/eslint that extend it (existing configs are never overwritten).',
        ];
    }

    if (state === 'bridge-unwired') {
        const lines = [`The ${SHIM_DIR_NAME}/ bridge exists — finish wiring it:`];

        for (const [
            tool,
            config,
        ] of [
            [
                'TypeScript',
                firstDrift(project, 'tsconfig'),
            ],
            [
                'ESLint',
                firstDrift(project, 'eslintConfig'),
            ],
        ] as Array<['TypeScript' | 'ESLint', OwnedConfig | null]>) {
            const guidance = describeToolGuidance(project, tool, config);

            if (guidance) {
                lines.push(...guidance.fix.map((line) => `    ${line}`));
            }
        }

        return lines;
    }

    return [];
}
