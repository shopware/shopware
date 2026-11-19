/**
 * Deterministic routing tests for scripts/classify.ts. No LLM involved.
 *
 *   node --test tests/routing/routing.test.ts
 *
 * A case is a folder with files.txt, diff.patch, meta.json and expected.json:
 *   { "signals": [...],      // every listed signal must be present
 *     "not_signals": [...],  // none of these may be present
 *     "guides": [...] }      // the selected guide set must equal this list (order ignored)
 */

import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { type Meta, classify, loadRulesFromIndex } from '../../scripts/classify.ts';

type Expected = {
    signals: string[];
    not_signals?: string[];
    guides: string[];
};

const HERE = fileURLToPath(new URL('.', import.meta.url));
const ROOT = resolve(HERE, '../../../../..');
const CLASSIFY = resolve(HERE, '../../scripts/classify.ts');
const rules = loadRulesFromIndex(resolve(HERE, '../../guides/index.json'));

const cases = readdirSync(HERE, { withFileTypes: true })
    .filter((entry) => entry.isDirectory() && existsSync(join(HERE, entry.name, 'expected.json')))
    .map((entry) => entry.name)
    .sort();

const read = (name: string, file: string): string => readFileSync(join(HERE, name, file), 'utf8');

test('routing cases exist', () => {
    assert.ok(cases.length > 0, `no routing cases found in ${HERE}`);
});

for (const name of cases) {
    test(`routing case ${name}`, () => {
        const expected = JSON.parse(read(name, 'expected.json')) as Expected;
        const profile = classify({
            files: read(name, 'files.txt').split('\n'),
            diff: read(name, 'diff.patch'),
            base: 'trunk',
            meta: JSON.parse(read(name, 'meta.json')) as Meta,
            root: ROOT,
            rules,
        });

        assert.deepEqual(expected.signals.filter((signal) => !profile.signals.includes(signal)), [], 'missing signals');
        assert.deepEqual((expected.not_signals ?? []).filter((signal) => profile.signals.includes(signal)), [], 'forbidden signals present');
        assert.deepEqual(profile.guides.map((guide) => guide.guide).sort(), [...expected.guides].sort(), 'selected guides');
    });
}

test('CLI entry point prints the profile as JSON', () => {
    const name = cases[0];
    const stdout = execFileSync(process.execPath, [
        CLASSIFY,
        '--files', join(HERE, name, 'files.txt'),
        '--diff', join(HERE, name, 'diff.patch'),
        '--meta', join(HERE, name, 'meta.json'),
        '--root', ROOT,
    ], { encoding: 'utf8' });
    const profile = JSON.parse(stdout) as { base_ref: string; rules_source: string; guides: unknown[] };

    assert.ok(stdout.endsWith('}\n'));
    assert.equal(profile.base_ref, 'trunk');
    assert.equal(profile.rules_source, 'working-tree');
    assert.ok(Array.isArray(profile.guides));
});
