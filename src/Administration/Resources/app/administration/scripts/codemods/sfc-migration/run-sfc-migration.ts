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
 *                      (only applies to fully- and partially-migrated components in --write mode)
 *
 * Examples:
 *   npm run codemod:sfc-migration -- src/app/component/base/sw-button
 *   npm run codemod:sfc-migration -- --write src/Resources/app/administration/src
 *   npm run codemod:sfc-migration -- --write --force src/Resources/app/administration/src
 *   npm run codemod:sfc-migration -- --write --delete-originals src/Resources/app/administration/src
 */

import { existsSync, readFileSync, readdirSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import commandLineArgs from 'command-line-args';
import getUsage from 'command-line-usage';
import { globSync } from 'glob';
import { Project, ScriptKind } from 'ts-morph';
import { mergeComponentFiles } from './generate-sfc';

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
        description: 'Replace source index.js with an SFC entry point and delete .html.twig.',
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
    try {
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
    } catch {
        return { path: null, candidates: [] };
    }
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
        `Shopware.Component.register('${componentName}', ${objectLiteralText});` +
        jsContent.slice(end)
    );
}

function quoteJsString(value: string): string {
    return JSON.stringify(value);
}

function buildIndexShim(componentName: string, sfc: string): string {
    const vueImportPath = `./${componentName}.vue`;

    if (/Shopware\.Component\.(register|extend)\s*\(/.test(sfc)) {
        return `import ${quoteJsString(vueImportPath)};\n`;
    }

    return [
        `import component from ${quoteJsString(vueImportPath)};`,
        '',
        `Shopware.Component.register(${quoteJsString(componentName)}, component);`,
        '',
    ].join('\n');
}

function replaceOriginalsWithEntryPoint(indexPath: string, twigPath: string, componentName: string, sfc: string): void {
    writeFileSync(indexPath, buildIndexShim(componentName, sfc), 'utf-8');
    rmSync(twigPath);
}

export function runMigration(targetDir: string, options: RunOptions): RunResult {
    const { dryRun = true, force = false, deleteOriginals = false } = options;

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
                stats.skipped++;

                if (twigCandidates.length > 1) {
                    report.push(`SKIP (ambiguous twig)  ${indexPath} [${twigCandidates.join(', ')}]`);
                    continue;
                }

                report.push(`SKIP (no twig)  ${indexPath}`);
                continue;
            }

            const twigContent = readFileSync(twigPath, 'utf-8');
            const normalisedJs = normaliseJsContent(jsContent, componentName);
            const result = mergeComponentFiles(twigContent, normalisedJs);

            switch (result.status) {
                case 'fully-migrated': {
                    const vuePath = join(dir, `${componentName}.vue`);
                    if (!dryRun && !force && existsSync(vuePath)) {
                        stats.skippedExisting++;
                        report.push(`SKIP (already exists)  ${vuePath}`);
                        break;
                    }
                    if (!dryRun) {
                        writeFileSync(vuePath, result.sfc, 'utf-8');
                        if (deleteOriginals) {
                            replaceOriginalsWithEntryPoint(indexPath, twigPath, componentName, result.sfc);
                            stats.deletedOriginals++;
                            report.push(`  replaced entrypoint  ${indexPath}`);
                            report.push(`  deleted original     ${twigPath}`);
                        }
                    }
                    stats.fullyMigrated++;
                    const fullyPrefix = dryRun ? '[DRY RUN] Would write: ' : '';
                    report.push(`✓  fully-migrated        ${fullyPrefix}${vuePath}`);
                    for (const warning of result.warnings) {
                        stats.elWarnings++;
                        report.push(`   ⚠  ${warning}`);
                    }
                    break;
                }
                case 'partially-migrated': {
                    const vuePath = join(dir, `${componentName}.vue`);
                    if (!dryRun && !force && existsSync(vuePath)) {
                        stats.skippedExisting++;
                        report.push(`SKIP (already exists)  ${vuePath}`);
                        break;
                    }
                    if (!dryRun) {
                        writeFileSync(vuePath, result.sfc, 'utf-8');
                        if (deleteOriginals) {
                            replaceOriginalsWithEntryPoint(indexPath, twigPath, componentName, result.sfc);
                            stats.deletedOriginals++;
                            report.push(`  replaced entrypoint  ${indexPath}`);
                            report.push(`  deleted original     ${twigPath}`);
                        }
                    }
                    stats.partiallyMigrated++;
                    const partialPrefix = dryRun ? '[DRY RUN] Would write: ' : '';
                    report.push(`~  partially-migrated  [${result.blockers.join(', ')}]  ${partialPrefix}${vuePath}`);
                    const extendsBlocker = result.blockers.find((b) => b.startsWith('extends'));
                    if (extendsBlocker) {
                        const parentMatch = extendsBlocker.match(/\(parent: ([^)]+)\)/);
                        const parentName = parentMatch ? parentMatch[1] : 'unknown';
                        stats.extendsComponents++;
                        report.push(
                            `   ⚠  manually inline parent options from '${parentName}' before re-running codemod; see README.md`,
                        );
                    }
                    break;
                }
                case 'not-migratable': {
                    stats.notMigratable++;
                    report.push(`✗  not-migratable      [${result.blockers.join(', ')}]  ${indexPath}`);
                    break;
                }
            }
        } catch (err) {
            stats.errors = (stats.errors ?? 0) + 1;
            report.push(`ERROR  ${indexPath}: ${err instanceof Error ? err.message : String(err)}`);
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
