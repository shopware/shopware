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
import type { ConfigMode, ExtensionToolingProject } from './shared';

interface RenderOptions {
    verbose?: boolean;
}

/**
 * Concrete, location-aware "how to get this extension checked" steps — shared by
 * the check runner's `unmanaged` message and the setup report's next-steps.
 */
export function describeInclusionSteps(project: ExtensionToolingProject): string[] {
    if (project.vendor) {
        return [
            'Read-only vendor extension — checked through host-owned configs; findings are',
            'non-fatal (pass --strict-vendor to fail on them).',
        ];
    }

    if (project.basePath.startsWith('custom/plugins/')) {
        return [
            "It isn't checked with the Shopware preset yet. Bridge it with one command:",
            `    composer admin:setup-extension-tooling -- --shim=${project.name}`,
            'That generates a git-ignored .shopware-admin/ bridge and, if the plugin has no config',
            'yet, small committed tsconfig/eslint that extend it. If you already have configs, add',
            '    "extends": "./.shopware-admin/tsconfig.json"   (and import the eslint bridge).',
        ];
    }

    return [
        'It ships its own config. Compose the Shopware factory in it so the check can run:',
        "    import { shopwareAdminExtension } from '<administration>/extension-tooling/eslint.mjs';",
    ];
}

function seconds(run: ToolRunResult): string {
    return `${(run.durationMs / 1000).toFixed(1)}s`;
}

function statusLine(tool: string, run: ToolRunResult, mode: ConfigMode): string {
    const label = tool.padEnd(11, ' ');
    const meta = colors.dim(`${mode} · ${seconds(run)}`);

    switch (run.status) {
        case 'passed':
            return `${label}${colors.green('✔ passed')}       ${meta}`;
        case 'failed':
            return `${label}${colors.red(`✖ ${run.findings || 'some'} finding(s)`)}  ${meta}`;
        case 'unmanaged':
            return `${label}${colors.yellow('⊘ SKIPPED')}     ${colors.dim('(unmanaged)')}`;
        case 'no-files':
            return `${label}${colors.dim('· no lintable files')}`;
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

    const tools: Array<[string, ToolRunResult, ConfigMode]> = [
        [
            'TypeScript',
            result.typescript,
            result.tsMode,
        ],
        [
            'ESLint',
            result.eslint,
            result.eslintMode,
        ],
    ];
    let anyUnmanaged = false;

    for (const [
        tool,
        run,
        mode,
    ] of tools) {
        lines.push(`    ${statusLine(tool, run, mode)}`);
        anyUnmanaged = anyUnmanaged || run.status === 'unmanaged';

        const showOutput =
            run.status === 'failed' || run.status === 'tooling-error' || (verbose && run.status !== 'no-files');

        if (showOutput && run.output.trim() !== '') {
            lines.push(indent(run.output.trim(), '      '));
        }
    }

    // Both tools skip for the same reason; explain it once per extension.
    if (anyUnmanaged) {
        lines.push(...describeInclusionSteps(result.project).map((step) => colors.dim(`      ${step}`)));
    }

    return lines;
}

function summaryCell(run: ToolRunResult): string {
    switch (run.status) {
        case 'passed':
            return 'passed';
        case 'failed':
            return `${run.findings || 'some'} finding(s)`;
        case 'unmanaged':
            return 'skipped';
        case 'no-files':
            return 'no files';
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
        summaryCell(result.typescript),
        summaryCell(result.eslint),
    ]);
    const widths = headers.map((header, column) => Math.max(header.length, ...rows.map((row) => row[column].length)));
    const format = (cells: string[]): string =>
        `  ${cells.map((cell, column) => cell.padEnd(widths[column], ' ')).join('   ')}`;
    const separator = `  ${colors.dim('─'.repeat(widths.reduce((sum, width) => sum + width + 3, 0)))}`;

    return [
        '',
        separator,
        colors.dim(format(headers)),
        ...rows.map((row) => format(row)),
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

    for (const extension of result.results) {
        lines.push(...renderExtension(extension, options.verbose === true));
    }

    for (const warning of result.warnings) {
        lines.push(colors.yellow(`\nWarning: ${warning}`));
    }

    for (const diagnostic of result.fatalDiagnostics) {
        lines.push(colors.red(`\nError: ${diagnostic}`));
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
    const lines = [colors.bold(`Administration extension tooling — ${projects.length} extension(s)`)];

    if (options.shim) {
        const shim = options.shim;
        const justBridged = projects.filter(
            (project) => (project.name === shim || project.technicalNames.includes(shim)) && project.bridged,
        );

        for (const project of justBridged) {
            lines.push(
                '',
                colors.green(`✔ Bridged ${project.name}. Its tsconfig / eslint.config.mjs now extend the generated`),
                colors.green('  .shopware-admin/ bridge (git-ignored). Commit them, edit freely — keep the "extends".'),
            );
        }
    }

    const ready = projects.filter(
        (project) => !project.bridged && project.tsMode === 'managed' && project.eslintMode === 'managed',
    );
    const bridged = projects.filter((project) => project.bridged);
    const custom = projects.filter(
        (project) => !project.bridged && (project.tsMode === 'custom' || project.eslintMode === 'custom'),
    );

    lines.push('');

    if (ready.length > 0) {
        lines.push(`  ${colors.green('✔ ready')}    ${ready.map((project) => project.name).join(', ')}`);
    }

    if (bridged.length > 0) {
        lines.push(`  ${colors.cyan('● bridged')}  ${bridged.map((project) => project.name).join(', ')}`);
    }

    if (custom.length > 0) {
        lines.push(
            `  ${colors.yellow('● custom')}   ${custom.map((project) => project.name).join(', ')}  ` +
                colors.dim('(ships own config)'),
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

    const needsInclusion = custom.filter((project) => !project.vendor && project.basePath.startsWith('custom/plugins/'));

    if (needsInclusion.length > 0) {
        lines.push('', colors.bold('  Next steps'));

        for (const project of needsInclusion.slice(0, 5)) {
            lines.push(`  ${colors.bold(project.name)}`, ...describeInclusionSteps(project).map((step) => `    ${step}`));
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
                    `ts:${project.tsMode} · eslint:${project.eslintMode}`,
            );
        }

        for (const instruction of result.instructions) {
            lines.push('', ...instruction.split('\n').map((line) => `    ${line}`));
        }
    }

    return lines.join('\n');
}
