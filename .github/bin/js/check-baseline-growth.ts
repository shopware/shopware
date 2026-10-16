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
 * A rule without entries on the base commit is new to the baseline. That is how a rule joins the gate
 * with its existing violations, so it passes, but only when the pull request also changes one of the
 * `--config` files. Those have a code owner, so turning on a rule still needs a review.
 *
 * ESLint only reports unused suppressions for the files it lints, so the entries of a deleted file stay
 * behind and would let a new violation of the same rule pass. The check fails on them; `npm run lint:fix`
 * removes them. knip needs no such check, its baseline preprocessor already fails on fixed entries.
 */

import { execFileSync, spawnSync } from 'node:child_process';
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

export const isNewRule = ({ base, head }: Row): boolean => base === 0 && head > 0;

export const grownRows = (rows: Row[], configChanged: boolean): Row[] =>
    rows.filter((row) => row.head > row.base && !(configChanged && isNewRule(row)));

// ESLint stores the paths relative to the folder it runs in, which is the folder of the baseline
export const staleFiles = (path: string, content: string, exists: (file: string) => boolean): string[] => {
    if (path.endsWith('knip-baseline.json')) {
        return [];
    }

    return Object.keys(JSON.parse(content) as EslintSuppressions)
        .filter((file) => !exists(join(dirname(path), file)))
        .sort();
};

export const formatReport = (path: string, rows: Row[], isNew: boolean, configChanged = false): string => {
    const header = isNew ? `${path} (new baseline, not on the base commit)` : path;
    const grown = new Set(grownRows(rows, configChanged));
    const lines = rows.map((row) => {
        let marker = '';
        if (!isNew && grown.has(row)) {
            marker = '  <-- grew';
        } else if (!isNew && isNewRule(row)) {
            marker = '  <-- new rule';
        }

        return `  ${row.key}: ${isNew ? '-' : row.base} -> ${row.head}${marker}`;
    });

    return [header, ...lines].join('\n');
};

// git diff exits with 1 when the file differs from the base commit
const changedSince = (baseSha: string, path: string): boolean => {
    const result = spawnSync('git', ['diff', '--quiet', baseSha, '--', path], { stdio: 'ignore' });
    if (result.status !== 0 && result.status !== 1) {
        throw new Error(`Could not compare ${path} with ${baseSha}.`);
    }

    return result.status === 1;
};

const parseArgs = (args: string[]): { baseSha?: string; configs: string[]; paths: string[] } => {
    const parsed: { baseSha?: string; configs: string[]; paths: string[] } = { configs: [], paths: [] };
    for (let i = 0; i < args.length; i += 1) {
        if (args[i] === '--base') {
            i += 1;
            parsed.baseSha = args[i];
        } else if (args[i] === '--config') {
            i += 1;
            parsed.configs.push(args[i]);
        } else {
            parsed.paths.push(args[i]);
        }
    }

    return parsed;
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
    const { baseSha, configs, paths } = parseArgs(process.argv.slice(2));

    if (!baseSha || paths.length === 0) {
        process.stderr.write(
            'usage: check-baseline-growth.ts --base <sha> [--config <file>]... <baseline.json>...\n',
        );
        process.exit(2);
    }

    assertCommitExists(baseSha);
    const configChanged = configs.some((config) => changedSince(baseSha, config));

    let grew = false;
    const stale: string[] = [];

    for (const path of paths) {
        const headContent = existsSync(path) ? readFileSync(path, 'utf8') : null;
        const head = headContent === null ? {} : totalsFor(path, headContent);
        const baseContent = readFromBase(baseSha, path);
        const isNew = baseContent === null;
        const rows = compareTotals(isNew ? {} : totalsFor(path, baseContent), head);

        process.stdout.write(`${formatReport(path, rows, isNew, configChanged)}\n\n`);

        if (!isNew && grownRows(rows, configChanged).length > 0) {
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
