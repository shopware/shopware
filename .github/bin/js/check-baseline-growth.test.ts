import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { afterEach, beforeEach, test } from 'node:test';
import { eslintTotals, fallowTotals } from './check-baseline-growth.ts';

const SCRIPT = join(dirname(fileURLToPath(import.meta.url)), 'check-baseline-growth.ts');
const ESLINT = 'admin/eslint-suppressions.json';
const FALLOW = 'admin/fallow-baseline.json';
const CONFIG = 'admin/eslint.config.ts';
const FALLOW_CONFIG = 'admin/.fallowrc.json';

let repo: string;
let baseSha: string;

const git = (...args: string[]): string => execFileSync('git', args, { cwd: repo, encoding: 'utf8' }).trim();

const write = (path: string, content: unknown): void => {
    mkdirSync(join(repo, dirname(path)), { recursive: true });
    writeFileSync(join(repo, path), JSON.stringify(content));
};

const touch = (path: string): void => {
    mkdirSync(join(repo, dirname(path)), { recursive: true });
    writeFileSync(join(repo, path), '');
};

const fallowBaseline = (categories: Record<string, string[]>) => ({
    kind: 'dead-code',
    analysis_identity: { mode: 'syntactic', capabilities: [], completeness: 'complete' },
    identity: 'dc1',
    unused_files: [],
    unused_exports: [],
    unused_types: [],
    ...categories,
});

const run = (...args: string[]) => {
    const result = spawnSync(process.execPath, [SCRIPT, ...args], { cwd: repo, encoding: 'utf8' });

    return { status: result.status, output: `${result.stdout}${result.stderr}` };
};

beforeEach(() => {
    repo = mkdtempSync(join(tmpdir(), 'baseline-growth-'));
    git('init', '--quiet');
    git('config', 'user.email', 'test@example.com');
    git('config', 'user.name', 'Test');

    write(ESLINT, {
        'src/a.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, complexity: { count: 2 } },
        'src/b.js': { 'sw-core-rules/no-javascript-files': { count: 1 } },
    });
    write(
        FALLOW,
        fallowBaseline({
            unused_files: ['unused-file:build/old.ts'],
            unused_exports: ['unused-export:src/a.ts:unused'],
        }),
    );
    write(FALLOW_CONFIG, {});
    touch('admin/src/a.js');
    touch('admin/src/b.js');
    writeFileSync(join(repo, CONFIG), 'export default [];');
    git('add', '.');
    git('commit', '--quiet', '-m', 'base');
    baseSha = git('rev-parse', 'HEAD');
});

afterEach(() => {
    rmSync(repo, { recursive: true, force: true });
});

test('eslintTotals sums the counts per rule over all files', () => {
    assert.deepEqual(
        eslintTotals({
            'a.js': { complexity: { count: 2 }, 'max-params': { count: 1 } },
            'b.js': { complexity: { count: 3 } },
        }),
        { complexity: 5, 'max-params': 1 },
    );
});

test('fallowTotals counts the entries per issue category and skips the metadata', () => {
    assert.deepEqual(
        fallowTotals(
            fallowBaseline({
                unused_exports: ['unused-export:src/a.ts:x', 'unused-export:src/b.ts:y'],
                unused_class_members: ['unused-class-member:src/c.ts:default:z'],
            }),
        ),
        { unused_exports: 2, unused_class_members: 1 },
    );
});

test('passes and prints the totals when nothing changed', () => {
    const result = run('--base', baseSha, ESLINT, FALLOW);

    assert.equal(result.status, 0);
    assert.match(result.output, /complexity: 2 -> 2/);
    assert.match(result.output, /unused_exports: 1 -> 1/);
    assert.match(result.output, /Compared 2 baseline\(s\)/);
});

test('passes when a baseline shrinks', () => {
    write(ESLINT, { 'src/a.js': { 'sw-core-rules/no-javascript-files': { count: 1 } } });

    const result = run('--base', baseSha, ESLINT);

    assert.equal(result.status, 0);
    assert.match(result.output, /complexity: 2 -> 0/);
});

test('fails when a rule total grows', () => {
    write(ESLINT, {
        'src/a.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, complexity: { count: 3 } },
        'src/b.js': { 'sw-core-rules/no-javascript-files': { count: 1 } },
    });

    const result = run('--base', baseSha, ESLINT);

    assert.equal(result.status, 1);
    assert.match(result.output, /complexity: 2 -> 3 {2}<-- grew/);
});

test('passes when a Fallow baseline shrinks', () => {
    write(FALLOW, fallowBaseline({ unused_exports: ['unused-export:src/a.ts:unused'] }));

    const result = run('--base', baseSha, FALLOW);

    assert.equal(result.status, 0);
    assert.match(result.output, /unused_files: 1 -> 0/);
});

test('fails when a Fallow issue category grows', () => {
    write(
        FALLOW,
        fallowBaseline({
            unused_files: ['unused-file:build/old.ts'],
            unused_exports: ['unused-export:src/a.ts:unused', 'unused-export:src/c.ts:other'],
        }),
    );

    const result = run('--base', baseSha, FALLOW);

    assert.equal(result.status, 1);
    assert.match(result.output, /unused_exports: 1 -> 2 {2}<-- grew/);
});

test('passes a new Fallow issue category when .fallowrc.json changes too', () => {
    write(FALLOW_CONFIG, { rules: { 'unused-types': 'error' } });
    write(
        FALLOW,
        fallowBaseline({
            unused_files: ['unused-file:build/old.ts'],
            unused_exports: ['unused-export:src/a.ts:unused'],
            unused_types: ['unused-type:src/b.ts:Unused'],
        }),
    );

    const result = run('--base', baseSha, '--config', FALLOW_CONFIG, FALLOW);

    assert.equal(result.status, 0);
    assert.match(result.output, /unused_types: 0 -> 1 {2}<-- new rule/);
});

test('fails when a new rule appears in a baseline without a config change', () => {
    write(ESLINT, {
        'src/a.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, complexity: { count: 2 } },
        'src/b.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, 'max-params': { count: 1 } },
    });

    const result = run('--base', baseSha, '--config', CONFIG, ESLINT);

    assert.equal(result.status, 1);
    assert.match(result.output, /max-params: 0 -> 1 {2}<-- grew/);
});

test('passes a new rule when the config changes too, and marks it', () => {
    writeFileSync(join(repo, CONFIG), 'export default [{ rules: { "max-params": "error" } }];');
    write(ESLINT, {
        'src/a.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, complexity: { count: 2 } },
        'src/b.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, 'max-params': { count: 1 } },
    });

    const result = run('--base', baseSha, '--config', CONFIG, ESLINT);

    assert.equal(result.status, 0);
    assert.match(result.output, /max-params: 0 -> 1 {2}<-- new rule/);
});

test('fails when an existing rule grows, even with a config change', () => {
    writeFileSync(join(repo, CONFIG), 'export default [{ rules: { "max-params": "error" } }];');
    write(ESLINT, {
        'src/a.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, complexity: { count: 3 } },
        'src/b.js': { 'sw-core-rules/no-javascript-files': { count: 1 } },
    });

    const result = run('--base', baseSha, '--config', CONFIG, ESLINT);

    assert.equal(result.status, 1);
    assert.match(result.output, /complexity: 2 -> 3 {2}<-- grew/);
});

test('passes when a file moves without new violations', () => {
    rmSync(join(repo, 'admin/src/a.js'));
    touch('admin/src/moved/a.js');
    write(ESLINT, {
        'src/moved/a.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, complexity: { count: 2 } },
        'src/b.js': { 'sw-core-rules/no-javascript-files': { count: 1 } },
    });

    assert.equal(run('--base', baseSha, ESLINT).status, 0);
});

test('passes a baseline that does not exist on the base commit and marks it as new', () => {
    const storefront = 'storefront/eslint-suppressions.json';
    touch('storefront/src/main.js');
    write(storefront, { 'src/main.js': { 'sw-core-rules/no-javascript-files': { count: 1 } } });

    const result = run('--base', baseSha, storefront);

    assert.equal(result.status, 0);
    assert.match(result.output, /new baseline, not on the base commit/);
    assert.match(result.output, /no-javascript-files: - -> 1\n/);
});

test('fails on entries of a deleted file, which would hide a new violation of the same rule', () => {
    rmSync(join(repo, 'admin/src/b.js'));

    const result = run('--base', baseSha, ESLINT);

    assert.equal(result.status, 1);
    assert.match(result.output, /no longer exist. Run `npm run lint:fix`/);
    assert.match(result.output, /admin\/eslint-suppressions.json: src\/b.js/);
});

test('fails when the base commit cannot be read', () => {
    const result = run('--base', '0000000000000000000000000000000000000000', ESLINT);

    assert.notEqual(result.status, 0);
    assert.match(result.output, /Could not read the base commit/);
});

test('fails without a base commit or baseline files', () => {
    assert.equal(run('--base', baseSha).status, 2);
    assert.equal(run(ESLINT).status, 2);
});
