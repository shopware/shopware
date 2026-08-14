/**
 * @sw-package framework
 */

/**
 * SFC migration codemod: converts Options API components (`index.js` + `*.html.twig`) into native
 * setup SFCs (`<component-name>.vue` with `swDefinePublic`).
 *
 * Usage: npm run codemod:sfc-migration -- <path> [--write]
 *
 * Dry-run is the default. With `--write`, per component outcome:
 * - full:    writes the .vue, shrinks index.js to a re-export shim, deletes the twig template
 *            (unless another file still imports it — extend children import parent templates).
 * - partial: writes the .vue draft with TODO(sfc-migration) comments and keeps the original
 *            index.js + twig untouched next to it, so the component keeps running as-is.
 * - skipped: writes nothing; the report explains why.
 *
 * Every generated SFC must survive the real build transform and Vue's compiler (validate.ts)
 * before it is written — a non-compiling file is never produced, not even as a draft.
 */

import * as fs from 'fs';
import * as path from 'path';
import { globSync } from 'glob';
import { transformScript } from './transform-script';
import { transformTemplate } from './transform-template';
import { formatSfc, validateSfc } from './validate';

type Outcome = 'full' | 'partial' | 'skipped' | 'already-migrated' | 'error';

type ConvertInput = {
    jsSource: string;
    twigSource: string;
    componentName: string;
    vuePath: string;
    lang: 'js' | 'ts';
};

type ConvertResult = {
    outcome: 'full' | 'partial' | 'skipped';
    reasons: string[];
    sfc: string | null;
};

type ComponentReport = {
    name: string;
    dir: string;
    outcome: Outcome;
    reasons: string[];
};

type MigrationResult = {
    reports: ComponentReport[];
    stats: Record<'full' | 'partial' | 'skipped' | 'alreadyMigrated' | 'error', number>;
};

const KEBAB_NAME = /^[a-z][a-z0-9]*(-[a-z0-9]+)+$/;
const TEMPLATE_IMPORT = /import\s+\w+\s+from\s+['"]([^'"]+\.html\.twig)['"]/;
const ADMIN_SRC = path.resolve(__dirname, '../../../src');

/**
 * Converts one component. Pure apart from prettier config resolution — the integration spec and
 * the fixture snapshots run through exactly this function.
 */
async function convertComponent(input: ConvertInput): Promise<ConvertResult> {
    const template = transformTemplate(input.twigSource);

    if (template.template === null) {
        return { outcome: 'skipped', reasons: template.blockers, sfc: null };
    }

    const script = transformScript(input.jsSource, input.componentName);

    if (script.script === null) {
        return { outcome: 'skipped', reasons: script.blockers, sfc: null };
    }

    const langAttribute = input.lang === 'ts' ? ' lang="ts"' : '';
    const rawSfc = `<template>\n${template.template.trim()}\n</template>\n\n<script setup${langAttribute}>\n${script.script}\n</script>\n`;

    let formatted: string;

    try {
        formatted = await formatSfc(rawSfc);
    } catch (error) {
        return { outcome: 'skipped', reasons: [`prettier: ${(error as Error).message}`], sfc: null };
    }

    const validationError = validateSfc(formatted, input.vuePath);

    if (validationError !== null) {
        return { outcome: 'skipped', reasons: [`validation: ${validationError}`], sfc: null };
    }

    return {
        outcome: script.todos.length > 0 ? 'partial' : 'full',
        reasons: script.todos,
        sfc: formatted,
    };
}

/**
 * Maps every twig template to the files importing it. Guards `--write` against deleting a template
 * some other file (usually a `Component.extend` child) still imports.
 */
function collectTwigImporters(scanRoot: string): Map<string, Set<string>> {
    const importers = new Map<string, Set<string>>();
    const files = globSync('**/*.{js,ts}', { cwd: scanRoot, absolute: true, ignore: '**/node_modules/**' });

    for (const file of files) {
        const source = fs.readFileSync(file, 'utf8');

        for (const match of source.matchAll(/from\s+['"]([^'"]+\.html\.twig)['"]/g)) {
            if (!match[1].startsWith('.')) {
                continue;
            }

            const twigPath = path.resolve(path.dirname(file), match[1]);
            const set = importers.get(twigPath) ?? new Set<string>();

            set.add(file);
            importers.set(twigPath, set);
        }
    }

    return importers;
}

function buildIndexShim(originalSource: string, componentName: string): string {
    const packageMatch = originalSource.match(/@sw-package\s+([\w.-]+)/);
    // sw-deprecation-rules/private-feature-declarations requires a visibility annotation on the
    // re-export; carry the original one over (components default to @private).
    const visibility = originalSource.includes('@public') ? '@public' : '@private';
    const docblock = [
        '/**',
        ...(packageMatch
            ? [
                  ` * @sw-package ${packageMatch[1]}`,
                  ' *',
              ]
            : []),
        ` * ${visibility}`,
        ' */',
    ].join('\n');

    return `${docblock}\nexport { default } from './${componentName}.vue';\n`;
}

async function runMigration(
    targetDir: string,
    options: { write?: boolean; scanRoot?: string } = {},
): Promise<MigrationResult> {
    const write = options.write ?? false;
    const scanRoot = options.scanRoot ?? (targetDir.startsWith(ADMIN_SRC) ? ADMIN_SRC : targetDir);
    const twigImporters = collectTwigImporters(scanRoot);
    const indexFiles = globSync('**/index.{js,ts}', {
        cwd: targetDir,
        absolute: true,
        ignore: '**/node_modules/**',
    }).sort();

    const reports: ComponentReport[] = [];
    const stats: MigrationResult['stats'] = { full: 0, partial: 0, skipped: 0, alreadyMigrated: 0, error: 0 };
    const report = (name: string, dir: string, outcome: Outcome, reasons: string[] = []): void => {
        reports.push({ name, dir, outcome, reasons });
    };

    for (const indexFile of indexFiles) {
        const dir = path.dirname(indexFile);
        const name = path.basename(dir);
        const jsSource = fs.readFileSync(indexFile, 'utf8');

        // Files without a default-exported component config (registries, barrels) are not
        // components — they are not even reported.
        if (!/export\s+default\s/.test(jsSource)) {
            continue;
        }

        const templateImport = jsSource.match(TEMPLATE_IMPORT);

        if (!templateImport) {
            stats.skipped += 1;
            report(name, dir, 'skipped', ['no template import (render function or inherited template)']);
            continue;
        }

        if (!templateImport[1].startsWith('./')) {
            stats.skipped += 1;
            report(name, dir, 'skipped', ['template imported from outside the component directory']);
            continue;
        }

        if (!KEBAB_NAME.test(name)) {
            stats.skipped += 1;
            report(name, dir, 'skipped', ['directory name is not multi-segment kebab-case']);
            continue;
        }

        const twigPath = path.resolve(dir, templateImport[1]);

        if (!fs.existsSync(twigPath)) {
            stats.skipped += 1;
            report(name, dir, 'skipped', ['template file not found']);
            continue;
        }

        const vuePath = path.join(dir, `${name}.vue`);

        if (fs.existsSync(vuePath)) {
            stats.alreadyMigrated += 1;
            report(name, dir, 'already-migrated');
            continue;
        }

        let result: ConvertResult;

        try {
            result = await convertComponent({
                jsSource,
                twigSource: fs.readFileSync(twigPath, 'utf8'),
                componentName: name,
                vuePath,
                lang: indexFile.endsWith('.ts') ? 'ts' : 'js',
            });
        } catch (error) {
            stats.error += 1;
            report(name, dir, 'error', [(error as Error).message]);
            continue;
        }

        stats[result.outcome] += 1;

        if (write && result.sfc !== null) {
            fs.writeFileSync(vuePath, result.sfc);

            if (result.outcome === 'full') {
                fs.writeFileSync(indexFile, buildIndexShim(jsSource, name));

                const externalImporters = [...(twigImporters.get(twigPath) ?? [])].filter(
                    (importer) => importer !== indexFile,
                );

                if (externalImporters.length === 0) {
                    fs.rmSync(twigPath);
                } else {
                    result.reasons.push(`twig kept: still imported by ${externalImporters.length} other file(s)`);
                }
            }
        }

        report(name, dir, result.outcome, result.reasons);
    }

    return { reports, stats };
}

function printReport(result: MigrationResult, targetDir: string, write: boolean): void {
    const histogram = new Map<string, number>();

    for (const entry of result.reports) {
        const reasons = entry.reasons.length > 0 ? `  ${entry.reasons.join(', ')}` : '';

        console.log(
            `${entry.outcome.padEnd(17)} ${entry.name.padEnd(45)} ${path.relative(targetDir, entry.dir) || '.'}${reasons}`,
        );

        if (entry.outcome === 'partial' || entry.outcome === 'skipped') {
            for (const reason of entry.reasons) {
                histogram.set(reason, (histogram.get(reason) ?? 0) + 1);
            }
        }
    }

    if (histogram.size > 0) {
        console.log('\nReasons (by frequency):');

        for (const [
            reason,
            count,
        ] of [...histogram.entries()].sort((a, b) => b[1] - a[1])) {
            console.log(`  ${String(count).padStart(4)}  ${reason}`);
        }
    }

    const { stats } = result;
    const total = stats.full + stats.partial + stats.skipped + stats.alreadyMigrated + stats.error;

    console.log(
        `\n${total} components: ${stats.full} full, ${stats.partial} partial, ${stats.skipped} skipped, ` +
            `${stats.alreadyMigrated} already migrated, ${stats.error} errors${write ? '' : ' (dry run — nothing written)'}`,
    );
}

function main(): void {
    const args = process.argv.slice(2);
    const write = args.includes('--write');
    const positional = args.filter((arg) => !arg.startsWith('--'));
    const unknownFlags = args.filter((arg) => arg.startsWith('--') && arg !== '--write');

    if (positional.length !== 1 || unknownFlags.length > 0) {
        console.error('Usage: npm run codemod:sfc-migration -- <path> [--write]');
        process.exitCode = 1;
        return;
    }

    const targetDir = path.resolve(positional[0]);

    if (!fs.existsSync(targetDir) || !fs.statSync(targetDir).isDirectory()) {
        console.error(`Not a directory: ${targetDir}`);
        process.exitCode = 1;
        return;
    }

    runMigration(targetDir, { write })
        .then((result) => {
            printReport(result, targetDir, write);
            process.exitCode = result.stats.error > 0 ? 1 : 0;
        })
        .catch((error) => {
            console.error(error);
            process.exitCode = 1;
        });
}

if (require.main === module) {
    main();
}

export { convertComponent, runMigration, type ConvertInput, type ConvertResult, type MigrationResult };
