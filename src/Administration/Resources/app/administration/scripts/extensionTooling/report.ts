/**
 * @sw-package framework
 *
 * Human-readable rendering for `admin:check-extensions`. Pure: takes the silent
 * `CheckExtensionsResult` and returns the full report string. Color is applied
 * via picocolors, which disables itself on non-TTY output, so tests and CI logs
 * receive plain text and per-extension technical names are summarized to a
 * count instead of dumping every bundle.
 */

import colors from 'picocolors';
import type { CheckExtensionsResult, ExtensionCheckResult, ToolRunResult } from './check';
import type { SetupExtensionToolingResult } from './setup';
import { deriveExtensionState } from './shared';
import type { ExtensionToolingProject, ModeResolution } from './shared';

interface RenderOptions {
    verbose?: boolean;
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

function shimCommand(project: ExtensionToolingProject): string {
    return `composer admin:setup-extension-tooling -- --shim=${project.name}`;
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
export function describeNextStep(project: ExtensionToolingProject): string[] {
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
            `    ${shimCommand(project)}`,
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
                project.ts,
            ],
            [
                'ESLint',
                project.eslint,
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

function seconds(run: ToolRunResult): string {
    return `${(run.durationMs / 1000).toFixed(1)}s`;
}

function skipReason(tool: string, resolution: ModeResolution): string {
    const configNoun = tool === 'TypeScript' ? 'tsconfig' : 'config';

    if (resolution.reason === 'config-error') {
        return `own ${configNoun} fails to resolve`;
    }

    return tool === 'TypeScript'
        ? 'own tsconfig does not reach the Shopware type surface'
        : 'own config does not compose the Shopware factory';
}

function statusLine(tool: string, run: ToolRunResult, resolution: ModeResolution): string {
    const label = tool.padEnd(11, ' ');
    const meta = colors.dim(`${resolution.mode} · ${seconds(run)}`);

    switch (run.status) {
        case 'passed':
            return `${label}${colors.green('✔ passed')}       ${meta}`;
        case 'failed':
            return `${label}${colors.red(`✖ ${run.findings || 'some'} finding(s)`)}  ${meta}`;
        case 'unmanaged':
            return `${label}${colors.yellow('⊘ skipped')} — ${colors.dim(skipReason(tool, resolution))}`;
        case 'blocked':
            return `${label}${colors.yellow('⊘ blocked')}     ${colors.dim('(entity schema missing)')}`;
        case 'no-files':
            // An honest empty pass: nothing was checked, say so instead of a
            // bare green that reads as "my code type-checks".
            return tool === 'TypeScript'
                ? `${label}${colors.green('✔ passed')} ${colors.dim('(0 TypeScript files — .js is not type-checked)')}`
                : `${label}${colors.dim('· no lintable files')}`;
        default:
            return `${label}${colors.red('✖ TOOLING ERROR')}  ${colors.dim(seconds(run))}`;
    }
}

function indent(text: string, prefix: string): string {
    return text
        .split('\n')
        .map((line) => `${prefix}${line}`)
        .join('\n');
}

function renderExtension(result: ExtensionCheckResult, verbose: boolean): string[] {
    const location = result.project.vendor ? 'vendor' : result.project.basePath;
    const moduleCount = result.project.technicalNames.length;
    const moduleNote = moduleCount > 1 ? colors.dim(` (${moduleCount} modules)`) : '';
    const lines = [`\n  ${colors.bold(result.project.name)}${moduleNote}  ${colors.dim(location)}`];

    const tools: Array<['TypeScript' | 'ESLint', ToolRunResult, ModeResolution]> = [
        [
            'TypeScript',
            result.typescript,
            result.tsResolution,
        ],
        [
            'ESLint',
            result.eslint,
            result.eslintResolution,
        ],
    ];
    const state = deriveExtensionState(result.project);
    let anyUnmanaged = false;

    for (const [
        tool,
        run,
        resolution,
    ] of tools) {
        lines.push(`    ${statusLine(tool, run, resolution)}`);
        anyUnmanaged = anyUnmanaged || run.status === 'unmanaged';

        if (run.status === 'unmanaged') {
            const guidance = describeToolGuidance(result.project, tool, resolution);

            if (guidance) {
                lines.push(colors.dim(`      why: ${guidance.why}`));
                lines.push(...guidance.fix.map((line, index) => `      ${index === 0 ? 'fix: ' : '     '}${line}`));
            }

            if (verbose && resolution.probeOutput && resolution.probeOutput.trim() !== '') {
                lines.push(indent(resolution.probeOutput.trim(), '      '));
            }

            continue;
        }

        const showOutput =
            run.status === 'failed' || run.status === 'tooling-error' || (verbose && run.status !== 'no-files');

        if (showOutput && run.output.trim() !== '') {
            lines.push(indent(run.output.trim(), '      '));
        }
    }

    if (anyUnmanaged) {
        if (state === 'needs-bridge') {
            lines.push(...describeNextStep(result.project).map((step) => colors.dim(`      ${step}`)));
        } else if (state === 'vendor') {
            lines.push(...describeNextStep(result.project).map((step) => colors.dim(`      ${step}`)));
        } else if (state === 'platform') {
            lines.push(colors.dim('      platform bundle — its own config decides composition.'));
        }
    }

    return lines;
}

function summaryCell(run: ToolRunResult, tool: string): string {
    switch (run.status) {
        case 'passed':
            return 'passed';
        case 'failed':
            return `${run.findings || 'some'} finding(s)`;
        case 'unmanaged':
            return 'skipped';
        case 'blocked':
            return 'blocked';
        case 'no-files':
            return tool === 'TypeScript' ? 'passed*' : 'no files';
        default:
            return 'tool error';
    }
}

function renderSummary(results: ExtensionCheckResult[]): string[] {
    if (results.length === 0) {
        return [];
    }

    const headers = [
        'extension',
        'ts',
        'eslint',
    ];
    const rows = results.map((result) => [
        result.project.name,
        summaryCell(result.typescript, 'TypeScript'),
        summaryCell(result.eslint, 'ESLint'),
    ]);
    const widths = headers.map((header, column) => Math.max(header.length, ...rows.map((row) => row[column].length)));
    const format = (cells: string[]): string =>
        `  ${cells.map((cell, column) => cell.padEnd(widths[column], ' ')).join('   ')}`;
    const separator = `  ${colors.dim('─'.repeat(widths.reduce((sum, width) => sum + width + 3, 0)))}`;
    const hasVacuousPass = results.some((result) => result.typescript.status === 'no-files');

    return [
        '',
        separator,
        colors.dim(format(headers)),
        ...rows.map((row) => format(row)),
        ...(hasVacuousPass ? [colors.dim('  * no TypeScript files — .js is not type-checked')] : []),
    ];
}

function hasFindings(result: ExtensionCheckResult): boolean {
    return [
        result.typescript.status,
        result.eslint.status,
    ].some((status) => status === 'failed' || status === 'tooling-error');
}

export function renderCheckReport(result: CheckExtensionsResult, options: RenderOptions = {}): string {
    const lines = [colors.bold(`Administration extension check — ${result.results.length} extension(s)`)];

    // Cause before consequence: a fatal explains every blocked line below it.
    for (const diagnostic of result.fatalDiagnostics) {
        lines.push(colors.red(`\nError: ${diagnostic}`));
    }

    for (const extension of result.results) {
        lines.push(...renderExtension(extension, options.verbose === true));
    }

    for (const warning of result.warnings) {
        lines.push(colors.yellow(`\nWarning: ${warning}`));
    }

    lines.push(...renderSummary(result.results));

    const withFindings = result.results.filter(hasFindings).length;
    const skipped = result.results.filter(
        (extension) => extension.typescript.status === 'unmanaged' && extension.eslint.status === 'unmanaged',
    ).length;
    const paint = result.exitCode === 0 ? colors.green : colors.red;
    const glyph = result.exitCode === 0 ? '✔' : '✖';

    lines.push(
        '',
        paint(
            `${glyph} ${result.results.length} checked · ${withFindings} with findings · ${skipped} skipped · ` +
                `exit ${result.exitCode}`,
        ),
    );

    return lines.join('\n');
}

interface SetupRenderOptions {
    explain?: boolean;
    checkOnly?: boolean;
    shim?: string;
    /**
     * Shown on plain (flag-less) runs: composer swallows options placed before
     * "--", so this footer is both flag discovery and the safety net for a
     * swallowed flag that silently turned into a default run.
     */
    showFlagHint?: boolean;
}

function fileChangeLine(result: SetupExtensionToolingResult): string {
    const created = result.writes.filter((write) => write.state === 'created').length;
    const updated = result.writes.filter((write) => write.state === 'updated').length;
    const removed = result.staleFiles.length;

    if (created === 0 && updated === 0 && removed === 0) {
        return 'Configs up to date';
    }

    return [
        `${created} generated`,
        `${updated} updated`,
        ...(removed > 0 ? [`${removed} removed`] : []),
    ].join(', ');
}

export function renderSetupReport(result: SetupExtensionToolingResult, options: SetupRenderOptions = {}): string {
    const { projects } = result.manifest;
    const stateOf = new Map(
        projects.map((project) => [
            project.name,
            deriveExtensionState(project),
        ]),
    );
    const platform = projects.filter((project) => stateOf.get(project.name) === 'platform');
    const ownExtensions = projects.filter((project) => stateOf.get(project.name) !== 'platform');

    // The empty state must never read as a green "up to date": the most likely
    // cause is a stale var/plugins.json after installing or activating a plugin.
    if (ownExtensions.length === 0) {
        const lines = [
            colors.bold('Administration extension tooling — no extensions found'),
            '',
            '  No installed extension with Administration sources was discovered.',
            '  Discovery reads var/plugins.json. If you just installed or activated a',
            '  plugin, refresh it:',
            '',
            '      bin/console bundle:dump',
        ];

        if (platform.length > 0) {
            lines.push(
                '',
                colors.dim(
                    `  (Platform bundles like ${platform.map((project) => project.name).join(', ')} are always ` +
                        'covered and not listed here.)',
                ),
            );
        }

        for (const warning of result.warnings) {
            lines.push(colors.yellow(`  ⚠ ${warning}`));
        }

        return lines.join('\n');
    }

    const lines = [colors.bold(`Administration extension tooling — ${ownExtensions.length} extension(s)`)];

    if (options.shim) {
        const shim = options.shim;
        const justBridged = projects.filter(
            (project) => (project.name === shim || project.technicalNames.includes(shim)) && project.bridgePresent,
        );

        for (const project of justBridged) {
            if (stateOf.get(project.name) === 'bridged') {
                lines.push(
                    '',
                    colors.green(`✔ Bridged ${project.name}. Its tsconfig / eslint.config.mjs now extend the generated`),
                    colors.green('  .shopware-admin/ bridge (git-ignored). Commit them, edit freely — keep the "extends".'),
                );
            } else {
                lines.push(
                    '',
                    colors.green(`✔ Bridge created for ${project.name} at .shopware-admin/ — one step left:`),
                    ...describeNextStep(project)
                        .slice(1)
                        .map((line) => `  ${line}`),
                );
            }
        }
    }

    const ready = projects.filter((project) => stateOf.get(project.name) === 'ready');
    const bridged = projects.filter((project) => stateOf.get(project.name) === 'bridged');
    const unwired = projects.filter((project) => stateOf.get(project.name) === 'bridge-unwired');
    const custom = projects.filter((project) => {
        const state = stateOf.get(project.name);

        if (state === 'needs-bridge') {
            return true;
        }

        return state === 'vendor' && !project.bridgePresent && (project.tsconfig !== null || project.eslintConfig !== null);
    });
    const unverifiedBridged = bridged.some(
        (project) =>
            (project.tsconfig !== null && !project.ts.verified) ||
            (project.eslintConfig !== null && !project.eslint.verified),
    );

    lines.push('');

    if (ready.length > 0) {
        lines.push(`  ${colors.green('✔ ready')}    ${ready.map((project) => project.name).join(', ')}`);
    }

    if (bridged.length > 0) {
        lines.push(
            `  ${colors.cyan('● bridged')}  ${bridged.map((project) => project.name).join(', ')}  ` +
                colors.dim(
                    `(own configs compose the Shopware preset${
                        unverifiedBridged ? ' — unverified, run composer admin:check-extensions' : ''
                    })`,
                ),
        );
    }

    if (unwired.length > 0) {
        lines.push(
            `  ${colors.yellow('⚠ bridge unwired')}  ${unwired.map((project) => project.name).join(', ')}  ` +
                colors.dim("(bridge exists — own config doesn't compose it yet)"),
        );
    }

    if (custom.length > 0) {
        lines.push(
            `  ${colors.yellow('● custom')}   ${custom.map((project) => project.name).join(', ')}  ` +
                colors.dim('(ships own config)'),
        );
    }

    if (platform.length > 0) {
        lines.push(
            colors.dim(
                `  platform   ${platform.map((project) => project.name).join(', ')}  ` +
                    '(always checked with core tooling)',
            ),
        );
    }

    lines.push('', `  ${colors.dim(fileChangeLine(result))}`);

    if (result.manifest.rootConfigs.tsconfig === 'conflict') {
        lines.push(colors.yellow('  ⚠ root tsconfig.json is user-owned — run with --explain to integrate'));
    }

    if (result.manifest.rootConfigs.eslintConfig === 'conflict') {
        lines.push(colors.yellow('  ⚠ root eslint.config.mjs is user-owned — run with --explain to integrate'));
    }

    for (const warning of result.warnings) {
        lines.push(colors.yellow(`  ⚠ ${warning}`));
    }

    if (options.checkOnly && result.changed) {
        lines.push('', colors.red('  Setup is stale — re-run `composer admin:setup-extension-tooling`:'));

        for (const write of result.writes.filter((entry) => entry.state === 'created' || entry.state === 'updated')) {
            lines.push(colors.dim(`    would ${write.state === 'created' ? 'create' : 'update'}: ${write.file}`));
        }

        for (const staleFile of result.staleFiles) {
            lines.push(colors.dim(`    would delete: ${staleFile}`));
        }
    }

    const needsInclusion = projects.filter((project) =>
        [
            'needs-bridge',
            'bridge-unwired',
        ].includes(stateOf.get(project.name) as string),
    );

    if (needsInclusion.length > 0) {
        lines.push('', colors.bold('  Next steps'));

        for (const project of needsInclusion.slice(0, 5)) {
            lines.push(`  ${colors.bold(project.name)}`, ...describeNextStep(project).map((step) => `    ${step}`));
        }

        if (needsInclusion.length > 5) {
            lines.push(colors.dim(`  … and ${needsInclusion.length - 5} more — run with --explain`));
        }
    }

    lines.push('', colors.dim('  IDE setup: run with --explain for VS Code / Zed / PhpStorm config'));

    if (options.showFlagHint) {
        lines.push(
            colors.dim(
                '  Options need "--": composer admin:setup-extension-tooling -- --check | --explain | --shim=<name> | --help',
            ),
        );
    }

    if (options.explain) {
        lines.push(
            '',
            colors.bold('  Details'),
            `    Administration root: ${result.manifest.adminRoot}`,
            `    Entity schema: ${
                result.manifest.entitySchemaAvailable
                    ? 'available'
                    : 'stub (run composer admin:generate-entity-schema-types)'
            }`,
        );

        for (const project of projects) {
            const moduleCount = project.technicalNames.length;

            lines.push(
                `    - ${project.name} · ${moduleCount === 1 ? '1 module' : `${moduleCount} modules`} · ` +
                    `ts:${project.ts.mode} · eslint:${project.eslint.mode}`,
            );
        }

        lines.push(
            '',
            '    Own path aliases: declare them in tsconfig.aliases.json next to the plugin config,',
            '    e.g. { "MyPlugin/*": ["src/*"] } — the generated .shopware-admin/ bridge merges them',
            '    with the preset paths (tsconfig "paths" cannot be extended additively).',
        );

        for (const instruction of result.instructions) {
            lines.push('', ...instruction.split('\n').map((line) => `    ${line}`));
        }
    }

    return lines.join('\n');
}
