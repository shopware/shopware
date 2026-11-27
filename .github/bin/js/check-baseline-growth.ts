/**
 * Fails when the per-rule totals of an ESLint baseline (eslint-suppressions.json) or the per-category totals of a
 * Fallow baseline (fallow-baseline.json) grow compared with the base commit.
 * See src/Administration/Resources/app/administration/technical-docs/11-code-quality/01-lint-gate.md.
 */

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { parseArgs } from 'node:util';

type Suppressions = Record<string, Record<string, { count: number }>>;
type Baseline = Record<string, unknown>;

const isFallow = (path: string): boolean => path.endsWith('fallow-baseline.json');

const git = (...args: string[]): string =>
    execFileSync('git', args, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'] });

const readFromBase = (base: string, path: string): Baseline => {
    try {
        git('cat-file', '-e', `${base}:${path}`);
    } catch {
        return {};
    }

    return JSON.parse(git('show', `${base}:${path}`)) as Baseline;
};

const totals = (path: string, baseline: Baseline): Record<string, number> => {
    const result: Record<string, number> = {};
    if (isFallow(path)) {
        // Each finding category is a top-level array, next to metadata such as `kind`
        for (const [category, entries] of Object.entries(baseline)) {
            if (Array.isArray(entries) && entries.length > 0) {
                result[category] = entries.length;
            }
        }

        return result;
    }

    for (const rules of Object.values(baseline as Suppressions)) {
        for (const [rule, { count }] of Object.entries(rules)) {
            result[rule] = (result[rule] ?? 0) + count;
        }
    }

    return result;
};

const { values, positionals: paths } = parseArgs({
    options: { base: { type: 'string' }, config: { type: 'string', multiple: true } },
    allowPositionals: true,
});
const { base, config: configs = [] } = values;

if (!base || configs.length === 0 || paths.length === 0) {
    process.stderr.write('usage: check-baseline-growth.ts --base <sha> --config <file>... <baseline>...\n');
    process.exit(2);
}

// A rule that is new to a baseline may only come in together with a reviewed config change
const configChanged = git('diff', '--name-only', base, '--', ...configs).trim() !== '';
const failures: string[] = [];

for (const path of paths) {
    const head: Baseline = existsSync(path) ? JSON.parse(readFileSync(path, 'utf8')) : {};
    const baseTotals = totals(path, readFromBase(base, path));
    const headTotals = totals(path, head);

    console.log(path);
    for (const rule of [...new Set([...Object.keys(baseTotals), ...Object.keys(headTotals)])].sort()) {
        const before = baseTotals[rule] ?? 0;
        const after = headTotals[rule] ?? 0;
        const grew = after > before && !(before === 0 && configChanged);

        console.log(`  ${rule}: ${before} -> ${after}${grew ? '  <-- grew' : ''}`);
        if (grew) {
            failures.push(`${path}: ${rule} grew from ${before} to ${after}. Fix the new violations instead.`);
        }
    }

    // Entries of a deleted file would let a new violation of the same rule pass elsewhere.
    // `npm run lint:fallow` already fails on them for Fallow.
    for (const file of isFallow(path) ? [] : Object.keys(head)) {
        if (!existsSync(join(dirname(path), file))) {
            failures.push(`${path}: ${file} no longer exists. Run \`npm run lint:fix\` to remove its entries.`);
        }
    }
}

console.log(`Compared ${paths.length} baseline(s) with ${base}.`);

if (failures.length > 0) {
    process.stderr.write(`${failures.join('\n')}\n`);
    process.exit(1);
}
