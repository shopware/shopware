/**
 * Fail a pull request that grows a lint baseline.
 *
 * The Administration baselines (`eslint-suppressions.json`, `knip-baseline.json`) record existing
 * violations so only new code has to follow the gate rules. A pull request may shrink them, which
 * every cleanup does, but must not grow them. CODEOWNERS cannot tell the two apart, so this check
 * compares the totals per rule (ESLint) or per issue type (knip) with the base commit.
 *
 * Totals instead of per-file counts, because a moved or renamed file changes the keys of its entries
 * without adding a violation.
 *
 * A baseline that does not exist on the base commit is new. That is how a folder adopts the gate
 * (`npm run lint:baseline:init`), so it passes, and its totals are printed for the review.
 *
 * ESLint only reports unused suppressions for the files it lints, so the entries of a deleted file stay
 * behind and would let a new violation of the same rule pass. The check fails on them; `npm run lint:fix`
 * removes them. knip needs no such check, its baseline preprocessor already fails on fixed entries.
 */

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';

export type Totals = Record<string, number>;

type EslintSuppressions = Record<string, Record<string, { count: number }>>;

export type Row = {
    key: string;
    base: number;
    head: number;
};

export const eslintTotals = (suppressions: EslintSuppressions): Totals => {
    const totals: Totals = {};

    for (const rules of Object.values(suppressions)) {
        for (const [rule, { count }] of Object.entries(rules)) {
            totals[rule] = (totals[rule] ?? 0) + count;
        }
    }

    return totals;
};

// Entries look like `exports|src/file.ts|symbol`
export const knipTotals = (entries: string[]): Totals => {
    const totals: Totals = {};

    for (const entry of entries) {
        const [type] = entry.split('|');
        totals[type] = (totals[type] ?? 0) + 1;
    }

    return totals;
};

export const totalsFor = (path: string, content: string): Totals => {
    const parsed: unknown = JSON.parse(content);

    if (path.endsWith('knip-baseline.json')) {
        return knipTotals(parsed as string[]);
    }

    return eslintTotals(parsed as EslintSuppressions);
};

export const compareTotals = (base: Totals, head: Totals): Row[] => {
    const keys = [...new Set([...Object.keys(base), ...Object.keys(head)])].sort();

    return keys.map((key) => ({ key, base: base[key] ?? 0, head: head[key] ?? 0 }));
};

export const grownRows = (rows: Row[]): Row[] => rows.filter(({ base, head }) => head > base);

// ESLint stores the paths relative to the folder it runs in, which is the folder of the baseline
export const staleFiles = (path: string, content: string, exists: (file: string) => boolean): string[] => {
    if (path.endsWith('knip-baseline.json')) {
        return [];
    }

    return Object.keys(JSON.parse(content) as EslintSuppressions)
        .filter((file) => !exists(join(dirname(path), file)))
        .sort();
};

export const formatReport = (path: string, rows: Row[], isNew: boolean): string => {
    const header = isNew ? `${path} (new baseline, not on the base commit)` : path;
    const lines = rows.map(({ key, base, head }) => {
        const marker = !isNew && head > base ? '  <-- grew' : '';

        return `  ${key}: ${isNew ? '-' : base} -> ${head}${marker}`;
    });

    return [header, ...lines].join('\n');
};

// git reports a missing commit like a missing path, so an unknown commit would pass every baseline as new
const assertCommitExists = (baseSha: string): void => {
    try {
        execFileSync('git', ['cat-file', '-e', `${baseSha}^{commit}`], { stdio: 'ignore' });
    } catch {
        throw new Error(`Could not read the base commit ${baseSha}. Fetch it before running this check.`);
    }
};

// Returns null when the file does not exist on the base commit
const readFromBase = (baseSha: string, path: string): string | null => {
    try {
        return execFileSync('git', ['show', `${baseSha}:${path}`], {
            encoding: 'utf8',
            maxBuffer: 64 * 1024 * 1024,
            stdio: ['ignore', 'pipe', 'pipe'],
        });
    } catch (error) {
        const stderr = String((error as { stderr?: unknown }).stderr ?? '');

        if (/does not exist in|exists on disk, but not in/.test(stderr)) {
            return null;
        }

        throw new Error(`Could not read ${path} from ${baseSha}: ${stderr.trim()}`);
    }
};

const main = (): void => {
    const [flag, baseSha, ...paths] = process.argv.slice(2);

    if (flag !== '--base' || !baseSha || paths.length === 0) {
        process.stderr.write('usage: check-baseline-growth.ts --base <sha> <baseline.json>...\n');
        process.exit(2);
    }

    assertCommitExists(baseSha);

    let grew = false;
    const stale: string[] = [];

    for (const path of paths) {
        const headContent = existsSync(path) ? readFileSync(path, 'utf8') : null;
        const head = headContent === null ? {} : totalsFor(path, headContent);
        const baseContent = readFromBase(baseSha, path);
        const isNew = baseContent === null;
        const rows = compareTotals(isNew ? {} : totalsFor(path, baseContent), head);

        process.stdout.write(`${formatReport(path, rows, isNew)}\n\n`);

        if (!isNew && grownRows(rows).length > 0) {
            grew = true;
        }

        if (headContent !== null) {
            stale.push(...staleFiles(path, headContent, existsSync).map((file) => `${path}: ${file}`));
        }
    }

    process.stdout.write(`Compared ${paths.length} baseline(s) with ${baseSha}.\n`);

    if (stale.length > 0) {
        process.stderr.write(
            `These baseline entries belong to files that no longer exist. Run \`npm run lint:fix\` to remove them:\n` +
                `${stale.map((entry) => `  ${entry}`).join('\n')}\n`,
        );
    }

    if (grew) {
        process.stderr.write(
            'A baseline grew. Fix the new violations instead of adding them to the baseline, see\n' +
                'src/Administration/Resources/app/administration/technical-docs/11-code-quality/01-lint-gate.md\n',
        );
    }

    if (grew || stale.length > 0) {
        process.exit(1);
    }
};

// Run the script if called directly
if (import.meta.url === `file://${process.argv[1]}`) {
    main();
}
