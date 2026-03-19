/**
 * Runner: generates `.vue` SFCs from Options API components.
 *
 * Scans the given directory recursively for `index.js` files. Components that
 * use `export default {}` (instead of `Shopware.Component.register`) are
 * normalised automatically so the transformation logic can handle both styles.
 *
 * Usage:
 *   npx tsx scripts/codemods/sfc-migration/run-sfc-migration.ts <path>
 *
 * Examples:
 *   npx tsx run-sfc-migration.ts src/app/component/base/sw-button
 *   npx tsx run-sfc-migration.ts src/Resources/app/administration/src
 */

import { readFileSync, writeFileSync, readdirSync } from 'node:fs';
import { join, dirname, basename, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { globSync } from 'glob';
import { mergeComponentFiles } from './generate-sfc';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const targetArg = process.argv[2];

if (!targetArg) {
    console.error('Usage: npx tsx run-sfc-migration.ts <path>');
    console.error('  <path>  Directory to scan for index.js component files');
    process.exit(1);
}

const TARGET_DIR = resolve(targetArg);

function findTwigFile(dir: string): string | null {
    const entries = readdirSync(dir);
    const twig = entries.find((f) => f.endsWith('.html.twig'));
    return twig ? join(dir, twig) : null;
}

/**
 * Components in `src/app` export their options object directly via
 * `export default { … }` rather than calling `Shopware.Component.register`.
 * Wrap them so `transform-script.ts` can locate the options object via AST.
 */
function normaliseJsContent(jsContent: string, componentName: string): string {
    const exportDefaultMatch = jsContent.match(/^(export\s+default\s*)\{/m);
    if (!exportDefaultMatch) {
        return jsContent;
    }

    const replaced = jsContent.replace(
        /^export\s+default\s*\{/m,
        `Shopware.Component.register('${componentName}', {`,
    );

    // Close the register() call — replace the last `};` that ends the default export
    const lastSemicolon = replaced.lastIndexOf('};');
    if (lastSemicolon === -1) {
        return replaced;
    }

    return replaced.slice(0, lastSemicolon) + '});' + replaced.slice(lastSemicolon + 2);
}

const indexFiles = globSync('**/index.js', { cwd: TARGET_DIR, absolute: true });

const stats = { fullyMigrated: 0, partiallyMigrated: 0, notMigratable: 0, skipped: 0 };
const report: string[] = [];

for (const indexPath of indexFiles) {
    const jsContent = readFileSync(indexPath, 'utf-8');

    const dir = dirname(indexPath);
    const componentName = basename(dir);
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
            writeFileSync(vuePath, result.sfc, 'utf-8');
            stats.fullyMigrated++;
            report.push(`✓  fully-migrated        ${vuePath}`);
            break;
        }
        case 'partially-migrated': {
            const vuePath = join(dir, `${componentName}.vue`);
            writeFileSync(vuePath, result.sfc, 'utf-8');
            stats.partiallyMigrated++;
            report.push(`~  partially-migrated  [${result.blockers.join(', ')}]  ${vuePath}`);
            break;
        }
        case 'not-migratable': {
            stats.notMigratable++;
            report.push(`✗  not-migratable      [${result.blockers.join(', ')}]  ${indexPath}`);
            break;
        }
    }
}

console.log(report.join('\n'));
console.log(`
Migration Summary
=================
Fully migrated:      ${stats.fullyMigrated}
Partially migrated:  ${stats.partiallyMigrated}
Not migratable:      ${stats.notMigratable}
Skipped (no twig):   ${stats.skipped}
`);
