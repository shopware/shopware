/**
 * @sw-package framework
 */

/**
 * SFC migration codemod: converts Options API components (`index.js` + `*.html.twig`) into native
 * setup SFCs (`<component-name>.vue` with `swDefinePublic`). See README.md for the CLI contract and
 * the per-outcome write rules.
 *
 * Only a plain `Component.register` reaches the explicit replacement path. An extend child renders
 * against bindings its parent declares and an override template patches another component's markup,
 * so neither survives being written as a self-contained base SFC — and a directory no registration
 * resolves to could be either, so it gets a draft only.
 */

import * as fs from 'fs';
import * as path from 'path';
import { spawnSync } from 'child_process';
import { parse } from '@babel/parser';
import type * as t from '@babel/types';
import { convertComponent, type ConvertResult, type Outcome } from './convert-component';
import {
    collectComponentSourceIndex,
    isContained,
    type InlineOverride,
    type RegistrationKind,
    type SourceDiagnostic,
} from './component-source-model';

type ComponentReport = {
    name: string;
    dir: string;
    outcome: Outcome;
    registration: RegistrationKind | 'unregistered' | 'ambiguous';
    reasons: string[];
};

type MigrationResult = {
    reports: ComponentReport[];
    stats: Record<Outcome, number>;
    inlineOverrides: InlineOverride[];
    diagnostics?: SourceDiagnostic[];
};

const errorText = (error: unknown): string => (error instanceof Error ? error.message : String(error));

const KEBAB_NAME = /^[a-z][a-z0-9]*(-[a-z0-9]+)+$/;
const ADMIN_SRC = path.resolve(__dirname, '../../../src');
const COMPONENT_CLASSES: ComponentReport['registration'][] = [
    'register',
    'extend',
    'override',
    'unregistered',
    'ambiguous',
];

const SW_PACKAGE = /@sw-package\s+(\S+)/;
const PUBLIC_ANNOTATION = /@public\b/;

/**
 * The comments attached to the default export — the component's own docblock, which is where its
 * visibility annotation lives. Reading it from anywhere else in the file picks up the `@public` on
 * a prop's or method's JSDoc and would declare a `@private` component public.
 */
function exportDocblock(originalSource: string): string {
    try {
        const ast = parse(originalSource, { sourceType: 'module', plugins: ['typescript'] });
        const exportDefault = ast.program.body.find(
            (statement): statement is t.ExportDefaultDeclaration => statement.type === 'ExportDefaultDeclaration',
        );

        return (exportDefault?.leadingComments ?? []).map((comment) => comment.value).join('\n');
    } catch {
        return '';
    }
}

function buildIndexShim(originalSource: string, componentName: string): string {
    const sourceDocblock = exportDocblock(originalSource);
    // `@sw-package` sits either in that docblock or in a file-level one above the imports, so it
    // falls back to the whole file. Visibility never does: absent from the component's own docblock
    // means @private.
    const packageMatch = sourceDocblock.match(SW_PACKAGE) ?? originalSource.match(SW_PACKAGE);
    // sw-deprecation-rules/private-feature-declarations requires a visibility annotation on the
    // re-export; carry the original one over (components default to @private).
    const visibility = PUBLIC_ANNOTATION.test(sourceDocblock) ? '@public' : '@private';
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
 * Ordinary writes publish only the validated Vue draft. Replacing the legacy entry point is a
 * separate explicit phase; Twig is never deleted.
 *
 * `--write` runs against a clean working tree (see `findDirtyPaths`), so `git checkout` undoes
 * everything a partial run left behind — nothing here needs its own transaction.
 */
function writeComponent(input: {
    sfc: string;
    full: boolean;
    replaceOriginals: boolean;
    vuePath: string;
    indexFile: string;
    jsSource: string;
    name: string;
}): { ok: boolean; reasons: string[] } {
    try {
        fs.writeFileSync(input.vuePath, input.sfc);

        if (input.full && input.replaceOriginals) {
            fs.writeFileSync(input.indexFile, buildIndexShim(input.jsSource, input.name));

            return { ok: true, reasons: [`index.js replaced; ${input.name}.html.twig retained`] };
        }

        return { ok: true, reasons: [] };
    } catch (error) {
        return { ok: false, reasons: [`write failed: ${errorText(error)} (originals unchanged)`] };
    }
}

/**
 * Every write happens in a git working tree, so `git checkout` is the undo button — but only for a
 * tree that carried nothing else. Uncommitted work under the target would become indistinguishable
 * from what this run produced, so `--write` refuses it. Scoped to the target, and skipped entirely
 * outside a work tree, where there is nothing to protect.
 */
function findDirtyPaths(targetDir: string): string[] {
    const status = spawnSync(
        'git',
        [
            'status',
            '--porcelain',
            '--',
            targetDir,
        ],
        {
            cwd: targetDir,
            encoding: 'utf8',
        },
    );

    if (status.status !== 0) {
        return [];
    }

    return status.stdout.split('\n').filter((line) => line.trim() !== '');
}

async function runMigration(
    targetDir: string,
    options: { write?: boolean; replaceOriginals?: boolean; scanRoot?: string } = {},
): Promise<MigrationResult> {
    const write = options.write ?? false;
    const replaceOriginals = options.replaceOriginals ?? false;
    const scanRoot = options.scanRoot ?? (isContained(ADMIN_SRC, targetDir) ? ADMIN_SRC : targetDir);
    const index = collectComponentSourceIndex(scanRoot);
    const indexFiles = [...index.files.keys()]
        .filter(
            (file) =>
                isContained(targetDir, file) && (path.basename(file) === 'index.js' || path.basename(file) === 'index.ts'),
        )
        .sort();
    const targetFiles = new Set(indexFiles);

    const result: MigrationResult = {
        reports: [],
        stats: {
            full: 0,
            partial: 0,
            skipped: 0,
            'already-migrated': 0,
            error: index.diagnostics.filter((diagnostic) => !targetFiles.has(diagnostic.file) && diagnostic.isScanError)
                .length,
        },
        inlineOverrides: index.inlineOverrides,
        diagnostics: index.diagnostics,
    };
    // Counting here rather than at each call site makes "one report row is one stat" structural,
    // which matters once an outcome can still change after the conversion (a failing write).
    const report = (name: string, dir: string, outcome: Outcome, reasons: string[] = []): void => {
        const registrations = index.registrationsByDir.get(dir) ?? [];
        const registration: ComponentReport['registration'] =
            registrations.length > 1 ? 'ambiguous' : (registrations[0]?.kind ?? 'unregistered');

        result.reports.push({ name, dir, outcome, registration, reasons });
        result.stats[outcome] += 1;
    };

    for (const indexFile of indexFiles) {
        const dir = path.dirname(indexFile);
        const dirName = path.basename(dir);
        const registrations = index.registrationsByDir.get(dir) ?? [];
        const registration = registrations.length === 1 ? registrations[0] : undefined;
        const name = registration?.name ?? dirName;
        const scanDiagnostics = index.files.get(indexFile);
        const component = index.components.get(indexFile);

        // Reads and stats can throw too (permissions, dangling symlinks). One unreadable component
        // must not cost the report for every component processed after it.
        try {
            if (!scanDiagnostics) {
                report(name, dir, 'error', ['source file not found']);
                continue;
            }

            if (scanDiagnostics.length > 0) {
                report(
                    name,
                    dir,
                    'error',
                    scanDiagnostics.map((diagnostic) => diagnostic.message),
                );
                continue;
            }

            // Files without a default export are registries/barrels, not components.
            if (!component) {
                continue;
            }

            // How a component is registered decides whether its template stands on its own, so this
            // outranks every file-layout reason below. The template compiler accepts the undeclared
            // references either kind leaves behind, so the validation gate cannot catch it either.
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

            if (registrations.length > 1) {
                report(name, dir, 'skipped', [
                    'multiple registrations resolve to this directory — registration kind and replacement authority are ambiguous',
                ]);
                continue;
            }

            const templateDiagnostics = component.diagnostics.filter((diagnostic) => diagnostic.isTemplateBinding);

            if (templateDiagnostics.length > 0) {
                report(
                    name,
                    dir,
                    'skipped',
                    templateDiagnostics.map((diagnostic) => diagnostic.message),
                );
                continue;
            }

            if (!component.template) {
                report(name, dir, 'skipped', ['no template import (render function or inherited template)']);
                continue;
            }

            if (!KEBAB_NAME.test(name)) {
                report(name, dir, 'skipped', ['component name is not multi-segment kebab-case']);
                continue;
            }

            if (index.duplicateNames.has(name)) {
                report(name, dir, 'skipped', ['component name registered more than once']);
                continue;
            }

            // A name the directory does not carry is only trustworthy with a second source agreeing:
            // the template filename, which by convention equals the registered name.
            if (name !== dirName && path.basename(component.template.twigPath, '.html.twig') !== name) {
                report(name, dir, 'skipped', ['template filename does not match the registered component name']);
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
                    jsSource: component.source,
                    twigSource: fs.readFileSync(component.template.twigPath, 'utf8'),
                    componentName: name,
                    vuePath,
                    lang: indexFile.endsWith('.ts') ? 'ts' : 'js',
                    templateImportRange: component.template.importRange,
                });
            } catch (error) {
                report(name, dir, 'error', [errorText(error)]);
                continue;
            }

            // A directory with no registration is draft-only. Replacement is explicitly restricted
            // to a single plain registration, so ambiguous and extension components cannot replace
            // an entry point.
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
                replaceOriginals: replaceOriginals && registration?.kind === 'register',
                vuePath,
                indexFile,
                jsSource: component.source,
                name,
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

function printReport(result: MigrationResult, targetDir: string, write: boolean, replaceOriginals: boolean): void {
    const histogram = new Map<string, number>();
    const classes = new Map<ComponentReport['registration'], number>();

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
    const total = stats.full + stats.partial + stats.skipped + stats['already-migrated'] + stats.error;
    const split = COMPONENT_CLASSES.filter((componentClass) => classes.has(componentClass))
        .map((componentClass) => `${classes.get(componentClass)} ${componentClass}`)
        .join(' / ');
    const mode = write
        ? replaceOriginals
            ? ' (drafts written; eligible entry points replaced; Twig retained)'
            : ' (validated Vue drafts written; legacy sources retained)'
        : ' (dry run — nothing written)';

    console.log(
        `\n${total} components${split ? ` (${split})` : ''}: ${stats.full} full, ${stats.partial} partial, ` +
            `${stats.skipped} skipped, ${stats['already-migrated']} already migrated, ${stats.error} errors${mode}`,
    );

    if ((result.diagnostics ?? []).length > 0) {
        console.log('\nScan diagnostics:');
        result.diagnostics?.forEach((diagnostic) =>
            console.log(`  ${diagnostic.label}: ${diagnostic.file}: ${diagnostic.message}`),
        );
    }

    if (result.inlineOverrides.length > 0) {
        console.log(
            `${result.inlineOverrides.length} inline Component.override(...) configs found (reported only, not migratable)`,
        );
    }
}

function main(): void {
    const args = process.argv.slice(2);
    const occurrences = (flag: string): number => args.filter((arg) => arg === flag).length;
    const write = occurrences('--write') === 1;
    const replaceOriginals = occurrences('--replace-originals') === 1;
    const positional = args.filter((arg) => !arg.startsWith('--'));
    const unknownFlags = args.filter((arg) => arg.startsWith('--') && arg !== '--write' && arg !== '--replace-originals');

    if (
        positional.length !== 1 ||
        unknownFlags.length > 0 ||
        occurrences('--write') > 1 ||
        occurrences('--replace-originals') > 1 ||
        (replaceOriginals && !write)
    ) {
        console.error(
            'Usage: npm run codemod:sfc-migration -- <path> [--write] [--replace-originals] (replacement requires both flags)',
        );
        process.exitCode = 1;
        return;
    }

    const targetDir = path.resolve(positional[0]);

    if (!fs.existsSync(targetDir) || !fs.statSync(targetDir).isDirectory()) {
        console.error(`Not a directory: ${targetDir}`);
        process.exitCode = 1;
        return;
    }

    if (write) {
        const dirtyPaths = findDirtyPaths(targetDir);

        if (dirtyPaths.length > 0) {
            console.error(
                `Refusing to write into a dirty working tree. Commit or stash these first:\n${dirtyPaths
                    .map((line) => `  ${line}`)
                    .join('\n')}`,
            );
            process.exitCode = 1;
            return;
        }
    }

    runMigration(targetDir, { write, replaceOriginals })
        .then((result) => {
            printReport(result, targetDir, write, replaceOriginals);
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
