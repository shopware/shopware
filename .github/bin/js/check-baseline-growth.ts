/**
 * Fails when the per-rule totals of an ESLint baseline (eslint-suppressions.json) grow compared with the base commit.
 * See src/Administration/Resources/app/administration/technical-docs/11-code-quality/01-lint-gate.md.
 */

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { parseArgs } from 'node:util';

type Suppressions = Record<string, Record<string, { count: number }>>;

const git = (...args: string[]): string =>
    execFileSync('git', args, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'] });

const readFromBase = (base: string, path: string): Suppressions => {
    try {
        git('cat-file', '-e', `${base}:${path}`);
    } catch {
        return {};
    }

    return JSON.parse(git('show', `${base}:${path}`)) as Suppressions;
};

const totals = (suppressions: Suppressions): Record<string, number> => {
    const result: Record<string, number> = {};
    for (const rules of Object.values(suppressions)) {
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
    process.stderr.write('usage: check-baseline-growth.ts --base <sha> --config <file>... <eslint-suppressions.json>...\n');
    process.exit(2);
}

// A rule that is new to a baseline may only come in together with a reviewed config change
const configChanged = git('diff', '--name-only', base, '--', ...configs).trim() !== '';
const failures: string[] = [];

for (const path of paths) {
    const head: Suppressions = existsSync(path) ? JSON.parse(readFileSync(path, 'utf8')) : {};
    const baseTotals = totals(readFromBase(base, path));
    const headTotals = totals(head);

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

    // Entries of a deleted file would let a new violation of the same rule pass elsewhere
    for (const file of Object.keys(head)) {
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
