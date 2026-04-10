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
 *   --delete-originals Delete the source index.js and .html.twig after writing the .vue file
 *                      (only applies to fully- and partially-migrated components in --write mode)
 *
 * Examples:
 *   npm run codemod:sfc-migration -- src/app/component/base/sw-button
 *   npm run codemod:sfc-migration -- --write src/Resources/app/administration/src
 *   npm run codemod:sfc-migration -- --write --force src/Resources/app/administration/src
 *   npm run codemod:sfc-migration -- --write --delete-originals src/Resources/app/administration/src
 */

import { existsSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { globSync } from 'glob';
import { Project, ScriptKind } from 'ts-morph';
import { mergeComponentFiles } from './generate-sfc';

export interface RunOptions {
    dryRun: boolean;
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
}

export interface RunResult {
    stats: RunStats;
    report: string[];
}

export function findTwigFile(dir: string): string | null {
    const entries = readdirSync(dir);
    const twig = entries.find((f) => f.endsWith('.html.twig'));
    return twig ? join(dir, twig) : null;
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

export function runMigration(targetDir: string, options: RunOptions): RunResult {
    const { dryRun, force = false, deleteOriginals = false } = options;
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
    };
    const report: string[] = [];

    for (const indexPath of indexFiles) {
        const jsContent = readFileSync(indexPath, 'utf-8');

        const dir = dirname(indexPath);
        const componentName = dir.split('/').at(-1) ?? 'unknown';
        const twigPath = findTwigFile(dir);

        if (!twigPath) {
            stats.skipped++;
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
                        rmSync(indexPath);
                        rmSync(twigPath);
                        stats.deletedOriginals++;
                        report.push(`  deleted originals    ${indexPath}`);
                        report.push(`  deleted originals    ${twigPath}`);
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
                        rmSync(indexPath);
                        rmSync(twigPath);
                        stats.deletedOriginals++;
                        report.push(`  deleted originals    ${indexPath}`);
                        report.push(`  deleted originals    ${twigPath}`);
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
                    report.push(`   ⚠  manually inline parent options from '${parentName}' before re-running codemod; see README.md`);
                }
                break;
            }
            case 'not-migratable': {
                stats.notMigratable++;
                report.push(`✗  not-migratable      [${result.blockers.join(', ')}]  ${indexPath}`);
                break;
            }
        }
    }

    return { stats, report };
}

// Only execute when invoked directly as a script, not when imported by tests.
if (process.argv[1] === __filename) {
    const args = process.argv.slice(2);
    const targetArg = args.find((a) => !a.startsWith('--'));

    if (!targetArg) {
        console.error('Usage: npx tsx run-sfc-migration.ts [--dry-run | --write] [--force] [--delete-originals] <path>');
        console.error('  <path>               Directory to scan for index.js component files');
        console.error('  --dry-run            (default) Preview what would be written without writing files');
        console.error('  --write              Write .vue files to disk');
        console.error('  --force              Overwrite existing .vue files (default: skip if already exists)');
        console.error('  --delete-originals   Delete source index.js and .html.twig after writing the .vue file');
        process.exit(1);
    }

    const TARGET_DIR = resolve(targetArg);
    const dryRun = args.includes('--dry-run') || !args.includes('--write');
    const force = args.includes('--force');
    const deleteOriginals = args.includes('--delete-originals');

    const { stats, report } = runMigration(TARGET_DIR, { dryRun, force, deleteOriginals });

    console.log(report.join('\n'));
    console.log(`
Migration Summary
=================
Fully migrated:      ${stats.fullyMigrated}
Partially migrated:  ${stats.partiallyMigrated}
Not migratable:      ${stats.notMigratable}
Skipped (no twig):   ${stats.skipped}
Skipped (exists):    ${stats.skippedExisting}
Deleted originals:   ${stats.deletedOriginals}
Components with $el: ${stats.elWarnings}
Components (extends): ${stats.extendsComponents}
`);

    if (dryRun) {
        console.log('[DRY RUN] No files were written. Run with --write to apply.');
    }
}
