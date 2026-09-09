/**
 * @sw-package framework
 *
 * Migrates direct reads of the global `Shopware` object to `shopware:*` imports.
 *
 *     npm run codemod:shopware-virtual-modules -- --namespace=stores
 *     npm run codemod:shopware-virtual-modules -- --dry
 *
 * Three things are left alone: everything the Administration evaluates before `src/index.ts` assigns
 * `window.Shopware`, all of `src/core`, and specs, which exercise the global on purpose.
 *
 * `src/core` is excluded as a layer, not because the closure reaches it. It is the Vue-independent
 * framework code, it boots first, and some of it is bundled into the admin worker, where there is no
 * `window.Shopware` at all.
 */

import fs from 'node:fs';
import path from 'node:path';
import { globSync } from 'glob';
import { bootstrapClosure } from './bootstrap-closure';
import { ALL_FAMILIES, loadRegistry, transformSource, type EnabledFamilies, type Skip } from './transform';
import { transformSfc } from './sfc';

const SPECIFIERS: Record<string, string> = {
    utils: 'shopware:utils',
    data: 'shopware:data',
    mixins: 'shopware:mixins',
    stores: 'shopware:stores',
};

const administrationRoot = path.resolve(__dirname, '../../..');
const srcDir = path.join(administrationRoot, 'src');

const argv = process.argv.slice(2);
const dryRun = argv.includes('--dry');
const namespaceArgument = argv.find((argument) => argument.startsWith('--namespace='))?.split('=')[1];

if (namespaceArgument && !SPECIFIERS[namespaceArgument]) {
    console.error(`Unknown namespace "${namespaceArgument}". Pick one of: ${Object.keys(SPECIFIERS).join(', ')}.`);
    process.exit(1);
}

const skipped = bootstrapClosure(srcDir);
const registry = loadRegistry(administrationRoot);

/** One invocation touches one family, which keeps each commit reviewable on its own. */
const enabled: EnabledFamilies = namespaceArgument ? new Set([SPECIFIERS[namespaceArgument]]) : ALL_FAMILIES;

const files = globSync('**/*.{ts,js,vue}', {
    cwd: srcDir,
    absolute: true,
    ignore: [
        'core/**',
        '**/*.spec.{ts,js}',
        '**/*.spec.vue2.{ts,js}',
        '**/*.spec/**',
        '**/_fixtures/**',
        '**/*.d.ts',
    ],
}).filter((file) => !skipped.has(file));

let changedFiles = 0;
const rewriteCounts = new Map<string, number>();
const skips: Skip[] = [];
const failures: { file: string; message: string }[] = [];

files.forEach((file) => {
    const original = fs.readFileSync(file, 'utf8');

    if (!original.includes('Shopware.')) {
        return;
    }

    const transform = (code: string, fileName: string) => transformSource(code, fileName, registry, enabled);

    // One file the transform cannot handle must not take the whole run down: report it and move on.
    let result;

    try {
        result = file.endsWith('.vue') ? transformSfc(original, file, transform) : transform(original, file);
    } catch (error) {
        failures.push({ file, message: error instanceof Error ? error.message.split('\n')[0] : String(error) });

        return;
    }

    skips.push(...result.skips);

    result.rewrites.forEach((rewrite) =>
        rewriteCounts.set(rewrite.specifier, (rewriteCounts.get(rewrite.specifier) ?? 0) + 1),
    );

    if (result.rewrites.length === 0 || result.code === original) {
        return;
    }

    changedFiles += 1;

    if (!dryRun) {
        fs.writeFileSync(file, result.code);
    }
});

console.log(`${dryRun ? 'Would change' : 'Changed'} ${changedFiles} of ${files.length} candidate files.`);

[...rewriteCounts.entries()].sort().forEach(
    ([
        specifier,
        count,
    ]) => console.log(`  ${specifier}: ${count} rewrites`),
);

// A muted branch reports every access as unmigratable, which is an artefact of the run, not a finding.
const selectedSpecifier = namespaceArgument ? SPECIFIERS[namespaceArgument] : undefined;
const reasons = new Map<string, number>();

skips
    .filter((skip) => !selectedSpecifier || skip.specifier === selectedSpecifier)
    .forEach((skip) => reasons.set(skip.reason, (reasons.get(skip.reason) ?? 0) + 1));

if (reasons.size > 0) {
    console.log('\nLeft on the global:');
    [...reasons.entries()]
        .sort((left, right) => right[1] - left[1])
        .forEach(
            ([
                reason,
                count,
            ]) => console.log(`  ${count}x ${reason}`),
        );
}

if (failures.length > 0) {
    console.log(`\n${failures.length} file(s) the transform could not handle:`);
    failures.forEach(({ file, message }) => console.log(`  ${path.relative(srcDir, file)}: ${message}`));
}

console.log(`\n${skipped.size} files evaluated before the global exists were not considered, nor was src/core.`);

if (failures.length > 0) {
    process.exitCode = 1;
}
