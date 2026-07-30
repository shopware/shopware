/**
 * @sw-package framework
 *
 * Human-readable rendering for `admin:setup-extension-tooling`. Pure: takes the
 * setup result and returns the full report string, with color applied via
 * picocolors (support decided once at import time — FORCE_COLOR, a TTY, or
 * env.CI turn it on; NO_COLOR wins), so the specs strip ANSI before semantic
 * assertions.
 *
 * Two tables carry the whole output. COVERAGE maps each derived extension state
 * to its bucket label and the single next action; OWNERSHIP maps each generated
 * file class to its lifecycle note and whether its paths are worth listing. The
 * per-file remediation is deliberately not re-derived here — the generators
 * already push it as warnings naming the exact files, which this report prints.
 */

import colors from 'picocolors';
import type { SetupExtensionToolingResult } from './setup';
import {
    BRIDGE_ESLINT_SPECIFIER,
    BRIDGE_TSCONFIG_EXTENDS,
    DEFAULT_TOOLING_COMMANDS,
    SHIM_DIR_NAME,
    deriveExtensionState,
    firstDrift,
} from './shared';
import type { DerivedExtensionState, ExtensionToolingProject, ToolingCommands } from './shared';

interface SetupRenderOptions {
    checkOnly?: boolean;
    /**
     * Shown on plain (flag-less) runs: composer swallows options placed before
     * "--", so this footer is both flag discovery and the safety net for a
     * swallowed flag that silently turned into a default run.
     */
    showFlagHint?: boolean;
    /** Layout-aware command invocations for printed next steps (defaults to the composer scripts). */
    commands?: ToolingCommands;
}

type StateMap = Map<string, DerivedExtensionState>;

/**
 * Ownership class of a generated path, so a developer can tell the lifecycles
 * apart: the git-ignored machine-specific bridge, the plugin's own committable
 * configs, the same files inside a composer-managed extension (local only), and
 * the disposable host projections. Classes whose file count scales with the
 * number of extensions are summarized instead of listed by path.
 */
export type FileClass = 'bridge' | 'committable' | 'vendor-config' | 'host';

const OWNERSHIP: Record<FileClass, { list: boolean; note: string }> = {
    committable: { list: true, note: 'commit this' },
    'vendor-config': { list: true, note: 'local — restored by re-running setup' },
    host: { list: true, note: '' },
    bridge: { list: false, note: `git-ignored ${SHIM_DIR_NAME}/ bridge file(s) — never commit` },
};

export function classifyFile(file: string): FileClass {
    if (file.includes(`/${SHIM_DIR_NAME}/`)) {
        return 'bridge';
    }

    const base = file.split('/').pop() ?? '';

    if (base === 'tsconfig.json' || base === 'eslint.config.mjs') {
        if (file.startsWith('vendor/')) {
            return 'vendor-config';
        }

        // A bare filename is the generated root projection, not an extension's config.
        return file.includes('/') ? 'committable' : 'host';
    }

    return 'host';
}

const VENDOR_NOTE =
    'Vendor extension: files under vendor/ are local only — a composer update removes them, setup restores them.';

const COVERAGE: Record<
    DerivedExtensionState,
    {
        label: string;
        paint: (value: string) => string;
        note: string;
        action?: (commands: ToolingCommands) => string[];
    }
> = {
    bridged: {
        label: '● bridged',
        paint: colors.cyan,
        note: 'own configs compose the Shopware preset',
    },
    ready: {
        label: '✔ ready',
        paint: colors.green,
        note: 'covered through the generated root projection',
    },
    'bridge-unwired': {
        label: '⚠ bridge unwired',
        paint: colors.yellow,
        note: "bridge exists — own config doesn't compose it yet",
        action: () => [
            `add "extends": "${BRIDGE_TSCONFIG_EXTENDS}" to the tsconfig, and`,
            `import shopware from '${BRIDGE_ESLINT_SPECIFIER}'; export default [ ...shopware ]; to the eslint config.`,
        ],
    },
    'needs-bridge': {
        label: '⚠ not bridged',
        paint: colors.yellow,
        note: 'ships own config, no bridge yet',
        action: (commands) => [
            `bridges are generated automatically — run \`${commands.setup}\`;`,
            'if a ⚠ above says why the bridge was skipped, fix that first.',
        ],
    },
    platform: {
        label: 'platform',
        paint: colors.dim,
        note: 'always checked with core tooling',
    },
};

const COVERAGE_ORDER: DerivedExtensionState[] = [
    'bridged',
    'ready',
    'bridge-unwired',
    'needs-bridge',
    'platform',
];

/**
 * Printed on every run, including the empty state. The tooling is experimental,
 * so the surfaces an extension could come to depend on — the command name, its
 * flags, the generated-file layout and the manifest format — are deliberately
 * outside the backwards-compatibility promise until it is declared stable.
 */
function renderNotice(): string[] {
    return [
        colors.yellow('  EXPERIMENTAL — not covered by the backwards-compatibility promise.'),
        colors.dim('  The command name, its flags, the generated-file layout and the manifest'),
        colors.dim('  format can change in any release. Re-run setup after a Shopware update'),
        colors.dim('  and never hand-edit generated files.'),
    ];
}

/**
 * One line per non-empty state bucket, plus that bucket's single action. The
 * action is per bucket rather than per extension because it never varied by
 * extension; what does vary — the concrete trap a config fell into — comes from
 * the drift detail.
 */
function renderCoverage(
    projects: ExtensionToolingProject[],
    stateOf: StateMap,
    commands: ToolingCommands,
    freshlyBridged: Set<string>,
): string[] {
    const lines = [''];

    for (const state of COVERAGE_ORDER) {
        const row = COVERAGE[state];
        const members = projects.filter((project) => stateOf.get(project.name) === state);

        if (members.length === 0) {
            continue;
        }

        const names = members
            .map((project) => (freshlyBridged.has(project.name) ? `${project.name} (new)` : project.name))
            .join(', ');

        lines.push(`  ${row.paint(row.label)}  ${names}  ${colors.dim(`(${row.note})`)}`);

        if (!row.action) {
            continue;
        }

        lines.push(...row.action(commands).map((line) => colors.dim(`      → ${line}`)));

        for (const project of members) {
            const drift = firstDrift(project, 'tsconfig') ?? firstDrift(project, 'eslintConfig');

            if (drift?.detail) {
                lines.push(colors.dim(`      ${project.name}: ${drift.detail}`));
            }
        }

        if (members.some((project) => project.vendor)) {
            lines.push(colors.dim(`      ${VENDOR_NOTE}`));
        }
    }

    return lines;
}

const VERBS = {
    real: { created: 'generated', updated: 'updated', removed: 'removed' },
    check: { created: 'would create', updated: 'would update', removed: 'would delete' },
};

/**
 * What the run changed — or, under `--check`, would change. Paths a developer
 * must act on are listed individually; the git-ignored bridge files, whose
 * count scales with the number of extensions, are summarized.
 */
function renderChanges(result: SetupExtensionToolingResult, checkOnly: boolean, commands: ToolingCommands): string[] {
    const verbs = checkOnly ? VERBS.check : VERBS.real;
    const changes = [
        ...result.writes
            .filter((write) => write.state === 'created')
            .map((write) => ({ verb: verbs.created, file: write.file })),
        ...result.writes
            .filter((write) => write.state === 'updated')
            .map((write) => ({ verb: verbs.updated, file: write.file })),
        ...result.staleFiles.map((file) => ({ verb: verbs.removed, file })),
    ];

    if (changes.length === 0) {
        return [colors.dim('  Configs up to date')];
    }

    const lines = checkOnly ? [colors.red(`  Setup is stale — re-run \`${commands.setup}\`:`)] : [];
    const counts = new Map<FileClass, number>();

    for (const { verb, file } of changes) {
        const fileClass = classifyFile(file);
        const { list, note } = OWNERSHIP[fileClass];

        if (list) {
            lines.push(colors.dim(`    ${verb}: ${file}`) + (note ? colors.cyan(` [${note}]`) : ''));
        } else {
            counts.set(fileClass, (counts.get(fileClass) ?? 0) + 1);
        }
    }

    for (const [
        fileClass,
        count,
    ] of counts) {
        lines.push(colors.dim(`    ${count} ${OWNERSHIP[fileClass].note}`));
    }

    return lines;
}

export function renderSetupReport(result: SetupExtensionToolingResult, options: SetupRenderOptions = {}): string {
    const { projects } = result.manifest;
    const checkOnly = options.checkOnly === true;
    const commands = options.commands ?? DEFAULT_TOOLING_COMMANDS;
    const stateOf: StateMap = new Map(
        projects.map((project) => [
            project.name,
            deriveExtensionState(project),
        ]),
    );
    const own = projects.filter((project) => stateOf.get(project.name) !== 'platform');
    const created = result.writes.filter((write) => write.state === 'created');
    const freshlyBridged = new Set(
        projects
            .filter((project) =>
                created.some(
                    (write) => write.file.startsWith(`${project.basePath}/`) && classifyFile(write.file) === 'bridge',
                ),
            )
            .map((project) => project.name),
    );
    const lines = [
        colors.bold(
            own.length === 0
                ? 'Administration extension tooling — no extensions found'
                : `Administration extension tooling — ${own.length} extension(s)`,
        ),
        ...renderNotice(),
    ];

    // The empty state must never read as a green "up to date": the most likely
    // cause is a stale var/plugins.json after installing or activating a plugin.
    if (own.length === 0) {
        lines.push(
            '',
            '  No installed extension with Administration sources was discovered.',
            '  Discovery reads var/plugins.json — refresh it after installing or activating a plugin:',
            '',
            '      bin/console bundle:dump',
        );
    }

    lines.push(...renderCoverage(projects, stateOf, commands, freshlyBridged));
    lines.push('', ...renderChanges(result, checkOnly, commands));

    for (const [
        name,
        state,
    ] of [
        [
            'tsconfig.json',
            result.manifest.rootConfigs.tsconfig,
        ],
        [
            'eslint.config.mjs',
            result.manifest.rootConfigs.eslintConfig,
        ],
    ] as const) {
        if (state === 'conflict') {
            lines.push(colors.yellow(`  ⚠ root ${name} is user-owned — integration steps below`));
        }
    }

    for (const warning of result.warnings) {
        lines.push(colors.yellow(`  ⚠ ${warning}`));
    }

    lines.push(
        ...result.instructions.flatMap((instruction) => [
            '',
            ...instruction.split('\n').map((line) => `  ${line}`),
        ]),
    );

    if (options.showFlagHint) {
        lines.push(colors.dim(`  Options need "--": ${commands.setup} -- --check | --help`));
    }

    return lines.join('\n');
}
