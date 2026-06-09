/**
 * Runner: generates `.vue` SFCs from Options API components.
 *
 * Scans the given directory recursively for `index.js` files. Components that
 * use `export default {}` (instead of `Shopware.Component.register`) are
 * normalised automatically so the transformation logic can handle both styles.
 *
 * Usage (from src/Administration/Resources/app/administration/):
 *   npm run codemod:sfc-migration -- [--dry-run | --write] [--force] [--delete-originals] <path>
 *
 * Flags:
 *   --dry-run          (default) Preview what would be written without writing files
 *   --write            Write .vue files to disk
 *   --force            Overwrite existing .vue files (default: skip if already exists)
 *   --delete-originals Replace the source index.js with an SFC entry point and delete .html.twig
 *                      (only applies to fully-migrated components in --write mode)
 *
 * Examples:
 *   npm run codemod:sfc-migration -- src/app/component/base/sw-button
 *   npm run codemod:sfc-migration -- --write src/Resources/app/administration/src
 *   npm run codemod:sfc-migration -- --write --force src/Resources/app/administration/src
 *   npm run codemod:sfc-migration -- --write --delete-originals src/Resources/app/administration/src
 */

import { existsSync, readFileSync, readdirSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import commandLineArgs from 'command-line-args';
import getUsage from 'command-line-usage';
import { globSync } from 'glob';
import { Project, ScriptKind } from 'ts-morph';
import type { MergeResult } from './generate-sfc';
import { mergeComponentFiles } from './generate-sfc';
import { quoteJsString } from './string-literals';

export interface RunOptions {
    dryRun?: boolean;
    force?: boolean;
    deleteOriginals?: boolean;
}

export interface RunStats {
    fullyMigrated: number;
    partiallyMigrated: number;
    notMigratable: number;
    skipped: number;
    skippedExisting: number;
    deletedOriginals: number;
    elWarnings: number;
    extendsComponents: number;
    errors: number;
}

export interface RunResult {
    stats: RunStats;
    report: string[];
}

interface CliOptionDefinition {
    name: string;
    alias?: string;
    type: StringConstructor | BooleanConstructor;
    defaultOption?: boolean;
    typeLabel?: string;
    description: string;
}

interface RawCliOptions {
    help?: boolean;
    dryRun?: boolean;
    write?: boolean;
    force?: boolean;
    deleteOriginals?: boolean;
    path?: string;
}

export interface CliOptions {
    help: boolean;
    targetDir?: string;
    dryRun: boolean;
    force: boolean;
    deleteOriginals: boolean;
}

interface MigrationContext {
    indexPath: string;
    twigPath: string;
    componentName: string;
    vuePath: string;
}

const cliOptionDefinitions: CliOptionDefinition[] = [
    {
        name: 'help',
        alias: 'h',
        type: Boolean,
        description: 'Prints this help page.',
    },
    {
        name: 'dry-run',
        type: Boolean,
        description: '(default) Preview what would be written without writing files.',
    },
    {
        name: 'write',
        type: Boolean,
        description: 'Write .vue files to disk.',
    },
    {
        name: 'force',
        type: Boolean,
        description: 'Overwrite existing .vue files (default: skip if already exists).',
    },
    {
        name: 'delete-originals',
        type: Boolean,
        description: 'Replace source index.js and delete .html.twig for fully migrated components.',
    },
    {
        name: 'path',
        type: String,
        defaultOption: true,
        typeLabel: '<path>',
        description: 'Directory to scan for index.js component files.',
    },
];

const cliUsageSections = [
    {
        header: 'SFC Migration Codemod',
        content: 'Generates .vue SFCs from Options API components.',
    },
    {
        header: 'Synopsis',
        content: [
            '$ npm run codemod:sfc-migration -- [--dry-run | --write] [--force] [--delete-originals] <path>',
            '$ npm run codemod:sfc-migration -- --help',
        ],
    },
    {
        header: 'Options',
        optionList: cliOptionDefinitions,
    },
];

export function getCliUsage(): string {
    return getUsage(cliUsageSections);
}

export function parseCliOptions(argv: string[]): CliOptions {
    const options = commandLineArgs(cliOptionDefinitions, { argv, camelCase: true }) as RawCliOptions;

    return {
        help: options.help ?? false,
        targetDir: options.path ? resolve(options.path) : undefined,
        dryRun: options.dryRun ?? !options.write,
        force: options.force ?? false,
        deleteOriginals: options.deleteOriginals ?? false,
    };
}

function selectTwigFile(dir: string, componentName: string): { path: string | null; candidates: string[] } {
    const candidates = readdirSync(dir)
        .filter((entry) => entry.endsWith('.html.twig'))
        .sort();
    const exactMatch = `${componentName}.html.twig`;

    if (candidates.includes(exactMatch)) {
        return { path: join(dir, exactMatch), candidates };
    }

    if (candidates.length === 1) {
        return { path: join(dir, candidates[0]), candidates };
    }

    return { path: null, candidates };
}

export function findTwigFile(dir: string, componentName: string): string | null {
    return selectTwigFile(dir, componentName).path;
}

/**
 * Components in `src/app` export their options object directly via
 * `export default { … }` rather than calling `Shopware.Component.register`.
 * Wrap them so `transform-script.ts` can locate the options object via AST.
 *
 * Uses ts-morph AST to locate the exact text range of the export default
 * statement, avoiding false matches on other `};` patterns in the file.
 */
export function normaliseJsContent(jsContent: string, componentName: string): string {
    const project = new Project({
        useInMemoryFileSystem: true,
        compilerOptions: { allowJs: true },
        skipAddingFilesFromTsConfig: true,
    });
    const sourceFile = project.createSourceFile('component.js', jsContent, { scriptKind: ScriptKind.JS });

    const exportDefault = sourceFile.getExportAssignment((e) => !e.isExportEquals());
    if (!exportDefault) {
        return jsContent;
    }

    const start = exportDefault.getStart();
    const end = exportDefault.getEnd();
    const objectLiteralText = exportDefault.getExpression().getText();

    return (
        jsContent.slice(0, start) +
        `Shopware.Component.register(${quoteJsString(componentName)}, ${objectLiteralText});` +
        jsContent.slice(end)
    );
}

function buildIndexShim(componentName: string): string {
    const vueImportPath = `./${componentName}.vue`;

    // TODO: Silent ignore: delete-originals uses the directory name for the
    // shim registration. If the original index.js registered a different
    // literal component name, the generated entrypoint silently registers a
    // different component.
    return [
        `import component from ${quoteJsString(vueImportPath)};`,
        '',
        `Shopware.Component.register(${quoteJsString(componentName)}, component);`,
        '',
    ].join('\n');
}

function formatReportPath(filePath: string): string {
    const relativePath = relative(process.cwd(), filePath).replaceAll('\\', '/');

    if (relativePath === '') {
        return '.';
    }

    return relativePath.startsWith('.') ? relativePath : `./${relativePath}`;
}

function replaceOriginalsWithEntryPoint(indexPath: string, twigPath: string, componentName: string): void {
    writeFileSync(indexPath, buildIndexShim(componentName), 'utf-8');
    rmSync(twigPath);
}

function reportSkippedTwig(indexPath: string, twigCandidates: string[], stats: RunStats, report: string[]): void {
    stats.skipped++;
    const displayIndexPath = formatReportPath(indexPath);

    const skipMessage =
        twigCandidates.length > 1
            ? `SKIP (ambiguous twig)  ${displayIndexPath} [${twigCandidates.join(', ')}]`
            : `SKIP (no twig)  ${displayIndexPath}`;

    report.push(skipMessage);
}

function writeMigrationOutput(
    context: MigrationContext,
    result: MergeResult,
    options: Required<RunOptions>,
    stats: RunStats,
    report: string[],
): boolean {
    if (options.dryRun) {
        return true;
    }

    if (!options.force && existsSync(context.vuePath)) {
        stats.skippedExisting++;
        report.push(`SKIP (already exists)  ${formatReportPath(context.vuePath)}`);

        return false;
    }

    writeFileSync(context.vuePath, result.sfc, 'utf-8');

    // Partially migrated SFCs are review artifacts until their blockers are
    // resolved. Keep the original entry point active so mixins/extends backoff
    // components do not change runtime behavior under --delete-originals.
    if (!options.deleteOriginals || result.status !== 'fully-migrated') {
        return true;
    }

    replaceOriginalsWithEntryPoint(context.indexPath, context.twigPath, context.componentName);
    stats.deletedOriginals++;
    report.push(`  replaced entrypoint  ${formatReportPath(context.indexPath)}`);
    report.push(`  deleted original     ${formatReportPath(context.twigPath)}`);

    return true;
}

function getDryRunPrefix(options: Required<RunOptions>): string {
    return options.dryRun ? '[DRY RUN] Would write: ' : '';
}

function reportWarnings(warnings: string[], stats: RunStats, report: string[]): void {
    for (const warning of warnings) {
        stats.elWarnings++;
        report.push(`   ⚠  ${warning}`);
    }
}

function reportFullyMigrated(
    context: MigrationContext,
    result: MergeResult,
    options: Required<RunOptions>,
    stats: RunStats,
    report: string[],
): void {
    stats.fullyMigrated++;
    report.push(`✓  fully-migrated        ${getDryRunPrefix(options)}${formatReportPath(context.vuePath)}`);
    reportWarnings(result.warnings, stats, report);
}

function reportExtendsBlocker(blockers: string[], stats: RunStats, report: string[]): void {
    const extendsBlocker = blockers.find((blocker) => blocker.startsWith('extends'));

    if (!extendsBlocker) {
        return;
    }

    const parentMatch = extendsBlocker.match(/\(parent: ([^)]+)\)/);
    const parentName = parentMatch ? parentMatch[1] : 'unknown';

    stats.extendsComponents++;
    report.push(`   ⚠  manually inline parent options from '${parentName}' before re-running codemod; see README.md`);
}

function reportPartiallyMigrated(
    context: MigrationContext,
    result: MergeResult,
    options: Required<RunOptions>,
    stats: RunStats,
    report: string[],
): void {
    stats.partiallyMigrated++;
    report.push(
        `~  partially-migrated  [${result.blockers.join(', ')}]  ${getDryRunPrefix(options)}${formatReportPath(context.vuePath)}`,
    );
    if (options.deleteOriginals && !options.dryRun) {
        report.push(
            '   ⚠  kept originals because partial migration requires manual follow-up before replacing the entrypoint',
        );
    }
    reportExtendsBlocker(result.blockers, stats, report);
}

function reportNotMigratable(context: MigrationContext, result: MergeResult, stats: RunStats, report: string[]): void {
    stats.notMigratable++;
    report.push(`✗  not-migratable      [${result.blockers.join(', ')}]  ${formatReportPath(context.indexPath)}`);
}

function handleMigrationResult(
    context: MigrationContext,
    result: MergeResult,
    options: Required<RunOptions>,
    stats: RunStats,
    report: string[],
): void {
    if (result.status === 'not-migratable') {
        reportNotMigratable(context, result, stats, report);

        return;
    }

    if (!writeMigrationOutput(context, result, options, stats, report)) {
        return;
    }

    if (result.status === 'fully-migrated') {
        reportFullyMigrated(context, result, options, stats, report);

        return;
    }

    reportPartiallyMigrated(context, result, options, stats, report);
}

export function runMigration(targetDir: string, options: RunOptions): RunResult {
    const runOptions: Required<RunOptions> = {
        dryRun: options.dryRun ?? true,
        force: options.force ?? false,
        deleteOriginals: options.deleteOriginals ?? false,
    };

    if (!existsSync(targetDir)) {
        throw new Error(`Target path does not exist: ${targetDir}`);
    }

    if (!statSync(targetDir).isDirectory()) {
        throw new Error(`Target path must be a directory: ${targetDir}`);
    }

    const indexFiles = globSync('**/index.js', { cwd: targetDir, absolute: true });

    const stats: RunStats = {
        fullyMigrated: 0,
        partiallyMigrated: 0,
        notMigratable: 0,
        skipped: 0,
        skippedExisting: 0,
        deletedOriginals: 0,
        elWarnings: 0,
        extendsComponents: 0,
        errors: 0,
    };
    const report: string[] = [];

    for (const indexPath of indexFiles) {
        try {
            const jsContent = readFileSync(indexPath, 'utf-8');

            const dir = dirname(indexPath);
            const componentName = dir.split('/').at(-1) ?? 'unknown';
            const { path: twigPath, candidates: twigCandidates } = selectTwigFile(dir, componentName);

            if (!twigPath) {
                reportSkippedTwig(indexPath, twigCandidates, stats, report);
                continue;
            }

            const twigContent = readFileSync(twigPath, 'utf-8');
            const normalisedJs = normaliseJsContent(jsContent, componentName);
            const result = mergeComponentFiles(twigContent, normalisedJs);
            const context = {
                indexPath,
                twigPath,
                componentName,
                vuePath: join(dir, `${componentName}.vue`),
            };

            handleMigrationResult(context, result, runOptions, stats, report);
        } catch (err) {
            stats.errors = (stats.errors ?? 0) + 1;
            report.push(`ERROR  ${formatReportPath(indexPath)}: ${err instanceof Error ? err.message : String(err)}`);
        }
    }

    return { stats, report };
}

// Only execute when invoked directly as a script, not when imported by tests.
if (process.argv[1] === __filename) {
    let cliOptions: CliOptions;

    try {
        cliOptions = parseCliOptions(process.argv.slice(2));
    } catch (err) {
        console.error(err instanceof Error ? err.message : String(err));
        console.error(getCliUsage());
        process.exit(1);
    }

    if (cliOptions.help) {
        console.log(getCliUsage());
        process.exit(0);
    }

    if (!cliOptions.targetDir) {
        console.error(getCliUsage());
        process.exit(1);
    }

    if (!cliOptions.dryRun && cliOptions.deleteOriginals) {
        console.warn('WARNING: --delete-originals will permanently delete source files. Ensure git is clean.');
    }

    try {
        const { stats, report } = runMigration(cliOptions.targetDir, {
            dryRun: cliOptions.dryRun,
            force: cliOptions.force,
            deleteOriginals: cliOptions.deleteOriginals,
        });

        console.log(report.join('\n'));
        console.log(`
Migration Summary
=================
Fully migrated:       ${stats.fullyMigrated}
Partially migrated:   ${stats.partiallyMigrated}
Not migratable:       ${stats.notMigratable}
Skipped (no twig):    ${stats.skipped}
Skipped (exists):     ${stats.skippedExisting}
Deleted originals:    ${stats.deletedOriginals}
Components with $el:  ${stats.elWarnings}
Components (extends): ${stats.extendsComponents}
Errors:               ${stats.errors}
`);

        if (cliOptions.dryRun) {
            console.log('[DRY RUN] No files were written. Run with --write to apply.');
        }

        if (stats.errors > 0 || stats.notMigratable > 0) {
            process.exit(1);
        }
    } catch (err) {
        console.error(`ERROR: ${err instanceof Error ? err.message : String(err)}`);
        process.exit(1);
    }
}
