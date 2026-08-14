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
 * - error:   the conversion or one of the writes threw; the reason says what is on disk.
 *
 * Only a plain `Component.register` reaches the destructive path. An extend child renders against
 * bindings its parent declares and an override template patches another component's markup, so
 * neither survives being written as a self-contained base SFC — and a directory no registration
 * resolves to could be either, so it gets a draft only.
 *
 * Every generated SFC must survive the real build transform and Vue's compiler (validate.ts)
 * before it is written — a non-compiling file is never produced, not even as a draft. That gate
 * proves the output compiles, not that it behaves the same, so shapes that would compile into
 * different behaviour are refused by the transforms themselves.
 *
 * Writes are guarded per component: a failure is reported and the run continues, so one unwritable
 * file cannot cost the report for everything migrated before it.
 */

import * as fs from 'fs';
import * as path from 'path';
import { globSync } from 'glob';
import { convertComponent, type ConvertResult } from './convert-component';
import { collectComponentRegistry, type InlineOverride, type RegistrationKind } from './component-registry';

type Outcome = 'full' | 'partial' | 'skipped' | 'already-migrated' | 'error';

type ComponentClass = RegistrationKind | 'unregistered';

type ComponentReport = {
    name: string;
    dir: string;
    outcome: Outcome;
    registration: ComponentClass;
    reasons: string[];
};

type MigrationResult = {
    reports: ComponentReport[];
    stats: Record<'full' | 'partial' | 'skipped' | 'alreadyMigrated' | 'error', number>;
    inlineOverrides: InlineOverride[];
};

const STAT_KEY: Record<Outcome, keyof MigrationResult['stats']> = {
    full: 'full',
    partial: 'partial',
    skipped: 'skipped',
    'already-migrated': 'alreadyMigrated',
    error: 'error',
};

const errorText = (error: unknown): string => (error instanceof Error ? error.message : String(error));

const KEBAB_NAME = /^[a-z][a-z0-9]*(-[a-z0-9]+)+$/;
const TEMPLATE_IMPORT = /import\s+\w+\s+from\s+['"]([^'"]+\.html\.twig)['"]/;
const ADMIN_SRC = path.resolve(__dirname, '../../../src');
const COMPONENT_CLASSES: ComponentClass[] = [
    'register',
    'extend',
    'override',
    'unregistered',
];

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

/**
 * Tells apart the three things an existing `<name>.vue` can mean, none of which is "this component
 * finished migrating" — a completed migration leaves an index.js re-export the discovery pass does
 * not recognise as a component, so it is never rediscovered.
 */
function describeExistingSfc(vuePath: string, name: string): string {
    const existing = fs.readFileSync(vuePath, 'utf8');

    if (existing.includes('TODO(sfc-migration)')) {
        return `draft from a previous run: ${name}.vue still has TODO(sfc-migration) markers`;
    }

    if (existing.includes('swDefinePublic(')) {
        return `half-migrated: ${name}.vue exists but index.js still holds the component config`;
    }

    return `a ${name}.vue that this codemod did not generate already exists`;
}

/**
 * Performs the file mutations of one component and reports what ended up on disk. Nothing escapes:
 * a failing write must not abandon the run, because everything migrated after it would go
 * unreported and the filesystem would be the only record.
 *
 * The order — `.vue`, then the index.js shim, then the twig — is the only safe one: nothing is
 * destroyed before its replacement exists. The `.vue` is rolled back when the shim write fails, so
 * a permission error leaves the component exactly as it was rather than half-migrated, which the
 * `already-migrated` precheck would otherwise make permanent. A failing twig deletion is not rolled
 * back: that state is a working migration with a stale file, and undoing it would be worse.
 */
function writeComponent(input: {
    sfc: string;
    full: boolean;
    vuePath: string;
    indexFile: string;
    jsSource: string;
    twigPath: string;
    name: string;
    externalImporters: string[];
}): { ok: boolean; reasons: string[] } {
    try {
        fs.writeFileSync(input.vuePath, input.sfc);
    } catch (error) {
        return { ok: false, reasons: [`write failed at .vue write: ${errorText(error)} (on disk: nothing changed)`] };
    }

    if (!input.full) {
        return { ok: true, reasons: [] };
    }

    try {
        fs.writeFileSync(input.indexFile, buildIndexShim(input.jsSource, input.name));
    } catch (error) {
        let rolledBack = `on disk: nothing changed — ${input.name}.vue removed again`;

        try {
            fs.rmSync(input.vuePath);
        } catch (rollbackError) {
            rolledBack = `on disk: ${input.name}.vue written and could not be removed: ${errorText(rollbackError)}`;
        }

        return { ok: false, reasons: [`write failed at index.js shim: ${errorText(error)} (${rolledBack})`] };
    }

    if (input.externalImporters.length > 0) {
        return {
            ok: true,
            reasons: [`twig kept: still imported by ${input.externalImporters.length} other file(s)`],
        };
    }

    try {
        fs.rmSync(input.twigPath);
    } catch (error) {
        return {
            ok: false,
            reasons: [
                `twig deletion failed: ${errorText(error)}`,
                `the .vue and the index.js shim are written — only ${input.name}.html.twig is left over`,
            ],
        };
    }

    return { ok: true, reasons: [] };
}

async function runMigration(
    targetDir: string,
    options: { write?: boolean; scanRoot?: string } = {},
): Promise<MigrationResult> {
    const write = options.write ?? false;
    const scanRoot = options.scanRoot ?? (targetDir.startsWith(ADMIN_SRC) ? ADMIN_SRC : targetDir);
    const twigImporters = collectTwigImporters(scanRoot);
    const registry = collectComponentRegistry(scanRoot);
    const indexFiles = globSync('**/index.{js,ts}', {
        cwd: targetDir,
        absolute: true,
        ignore: '**/node_modules/**',
    }).sort();

    const result: MigrationResult = {
        reports: [],
        stats: { full: 0, partial: 0, skipped: 0, alreadyMigrated: 0, error: 0 },
        inlineOverrides: registry.inlineOverrides,
    };
    // Counting here rather than at each call site makes "one report row is one stat" structural,
    // which matters once an outcome can still change after the conversion (a failing write).
    const report = (name: string, dir: string, outcome: Outcome, reasons: string[] = []): void => {
        const registration = registry.byDir.get(dir)?.kind ?? 'unregistered';

        result.reports.push({ name, dir, outcome, registration, reasons });
        result.stats[STAT_KEY[outcome]] += 1;
    };

    for (const indexFile of indexFiles) {
        const dir = path.dirname(indexFile);
        const dirName = path.basename(dir);
        // The registration is the authority on the component's name; only directories nothing
        // registers fall back to their basename.
        const registration = registry.byDir.get(dir);
        const name = registration?.name ?? dirName;

        // Reads and stats can throw too (permissions, dangling symlinks). One unreadable component
        // must not cost the report for every component processed after it.
        try {
            const jsSource = fs.readFileSync(indexFile, 'utf8');

            // Files without a default-exported component config (registries, barrels) are not
            // components — they are not even reported.
            if (!/export\s+default\s/.test(jsSource)) {
                continue;
            }

            // How a component is registered decides whether its template stands on its own, so this
            // outranks every file-layout reason below. An extend child renders against bindings its
            // parent declares and an override template patches another component's markup — neither
            // survives being written as a self-contained base SFC, and the template compiler accepts
            // the undeclared references, so the validation gate cannot catch it either.
            if (registration?.kind === 'extend') {
                report(name, dir, 'skipped', [
                    registration.parent
                        ? `Component.extend child of '${registration.parent}' (inherits the parent template)`
                        : 'Component.extend child (inherits the parent template)',
                ]);
                continue;
            }

            if (registration?.kind === 'override') {
                report(name, dir, 'skipped', ["Component.override registration (patches another component's template)"]);
                continue;
            }

            const templateImport = jsSource.match(TEMPLATE_IMPORT);

            if (!templateImport) {
                report(name, dir, 'skipped', ['no template import (render function or inherited template)']);
                continue;
            }

            if (!templateImport[1].startsWith('./')) {
                report(name, dir, 'skipped', ['template imported from outside the component directory']);
                continue;
            }

            if (!KEBAB_NAME.test(name)) {
                report(name, dir, 'skipped', ['component name is not multi-segment kebab-case']);
                continue;
            }

            if (registry.duplicateNames.has(name)) {
                report(name, dir, 'skipped', ['component name registered more than once']);
                continue;
            }

            // A name the directory does not carry is only trustworthy with a second source agreeing:
            // the template filename, which by convention equals the registered name.
            if (name !== dirName && path.basename(templateImport[1], '.html.twig') !== name) {
                report(name, dir, 'skipped', ['template filename does not match the registered component name']);
                continue;
            }

            const twigPath = path.resolve(dir, templateImport[1]);

            if (!fs.existsSync(twigPath)) {
                report(name, dir, 'skipped', ['template file not found']);
                continue;
            }

            const vuePath = path.join(dir, `${name}.vue`);

            if (fs.existsSync(vuePath)) {
                report(name, dir, 'already-migrated', [describeExistingSfc(vuePath, name)]);
                continue;
            }

            let converted: ConvertResult;

            try {
                converted = await convertComponent({
                    jsSource,
                    twigSource: fs.readFileSync(twigPath, 'utf8'),
                    componentName: name,
                    vuePath,
                    lang: indexFile.endsWith('.ts') ? 'ts' : 'js',
                });
            } catch (error) {
                report(name, dir, 'error', [(error as Error).message]);
                continue;
            }

            // A directory no registration resolves to is also where an extend child hides when the
            // registering file sits outside the scan root, so it never takes the destructive path.
            // Expressed as an outcome downgrade rather than a condition on the write, so `full` keeps
            // meaning "index.js is replaced and the twig is deleted".
            const outcome = converted.outcome === 'full' && registration === undefined ? 'partial' : converted.outcome;

            if (outcome !== converted.outcome) {
                converted.reasons.push('no registration resolves to this directory — draft only, index.js and twig kept');
            }

            if (!write || converted.sfc === null) {
                report(name, dir, outcome, converted.reasons);
                continue;
            }

            const written = writeComponent({
                sfc: converted.sfc,
                full: outcome === 'full',
                vuePath,
                indexFile,
                jsSource,
                twigPath,
                name,
                externalImporters: [...(twigImporters.get(twigPath) ?? [])].filter((importer) => importer !== indexFile),
            });

            report(name, dir, written.ok ? outcome : 'error', [
                ...converted.reasons,
                ...written.reasons,
            ]);
        } catch (error) {
            report(name, dir, 'error', [`unexpected failure: ${errorText(error)}`]);
        }
    }

    return result;
}

function printReport(result: MigrationResult, targetDir: string, write: boolean): void {
    const histogram = new Map<string, number>();
    const classes = new Map<ComponentClass, number>();

    for (const entry of result.reports) {
        const reasons = entry.reasons.length > 0 ? `  ${entry.reasons.join(', ')}` : '';

        console.log(
            `${entry.outcome.padEnd(17)} ${entry.registration.padEnd(13)} ${entry.name.padEnd(45)} ` +
                `${path.relative(targetDir, entry.dir) || '.'}${reasons}`,
        );

        classes.set(entry.registration, (classes.get(entry.registration) ?? 0) + 1);

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
    const split = COMPONENT_CLASSES.filter((componentClass) => classes.has(componentClass))
        .map((componentClass) => `${classes.get(componentClass)} ${componentClass}`)
        .join(' / ');

    console.log(
        `\n${total} components${split ? ` (${split})` : ''}: ${stats.full} full, ${stats.partial} partial, ` +
            `${stats.skipped} skipped, ${stats.alreadyMigrated} already migrated, ${stats.error} errors` +
            `${write ? '' : ' (dry run — nothing written)'}`,
    );

    if (result.inlineOverrides.length > 0) {
        console.log(
            `${result.inlineOverrides.length} inline Component.override(...) configs found (reported only, not migratable)`,
        );
    }
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

export { runMigration, type MigrationResult };
