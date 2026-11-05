#!/usr/bin/env node
/**
 * @sw-package framework
 */

/**
 * Maintains the baseline of untagged tests that predate the `test-annotation` rule.
 *
 *   node baseline.js prune           drops entries of tests that were tagged, renamed or deleted
 *   node baseline.js add <spec>...   records the untagged tests of moved spec files, see the technical doc
 *   node baseline.js init            records every untagged test, only when no baseline exists yet
 *
 * Run from the Administration root. The CI check in .github/bin/js/check-baseline-growth.ts fails when the
 * baseline gains a test identity, so `add` only helps a test that keeps its name in its new place.
 */

const { existsSync, globSync, readFileSync, writeFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { parseForESLint } = require('@typescript-eslint/parser');
const { collectTestCases } = require('./test-cases');
const { baselineKey } = require('./baseline-file');

const KINDS = new Set([
    'regression',
    'behavior',
    'characterization',
]);
const BASELINE = resolve('test-annotation-baseline.json');
const SPEC_PATTERNS = [
    'src/**/*.spec.js',
    'src/**/*.spec.ts',
    'src/**/*.spec/*.spec.js',
    'src/**/*.spec/*.spec.ts',
];

/**
 * Counts the untagged tests of one spec file by identity.
 *
 * @example
 * untaggedTests('src/a.spec.js'); // { 'sw-a > should render': 1 }
 */
function untaggedTests(file) {
    const text = readFileSync(file, 'utf8');
    const { ast } = parseForESLint(text, {
        comment: true,
        range: true,
        loc: true,
        sourceType: 'module',
        ecmaVersion: 'latest',
        filePath: file,
    });
    const counts = {};

    collectTestCases(ast, text, ast.comments)
        .tests.filter(({ doc }) => !(doc?.tags ?? []).some((tag) => KINDS.has(tag.name)))
        .forEach(({ identity }) => {
            counts[identity] = (counts[identity] ?? 0) + 1;
        });

    return counts;
}

/**
 * A copy with sorted keys, so the baseline diff stays stable.
 */
function sorted(object) {
    return Object.fromEntries(
        Object.keys(object)
            .sort()
            .map((key) => [
                key,
                object[key],
            ]),
    );
}

/**
 * Writes the baseline without empty files.
 */
function write(baseline) {
    const entries = Object.entries(baseline)
        .filter(([, identities]) => Object.keys(identities).length > 0)
        .map(([file, identities]) => [
            file,
            sorted(identities),
        ]);

    writeFileSync(BASELINE, `${JSON.stringify(sorted(Object.fromEntries(entries)), null, 4)}\n`);
}

/**
 * The committed baseline, or an empty one.
 */
function readCurrent() {
    return existsSync(BASELINE) ? JSON.parse(readFileSync(BASELINE, 'utf8')) : {};
}

/**
 * Records every untagged test; only for the pull request that introduces the rule.
 */
function init() {
    if (existsSync(BASELINE)) {
        throw new Error(`${BASELINE} exists. Recording every untagged test again would accept the new ones too.`);
    }

    const files = SPEC_PATTERNS.flatMap((pattern) => globSync(pattern)).sort();
    write(
        Object.fromEntries(
            files.map((file) => [
                baselineKey(BASELINE, resolve(file)),
                untaggedTests(file),
            ]),
        ),
    );
}

/**
 * Lowers every entry to the untagged tests that still match it, and drops deleted files.
 */
function prune() {
    const baseline = readCurrent();

    Object.keys(baseline).forEach((file) => {
        const current = existsSync(resolve(file)) ? untaggedTests(file) : {};

        baseline[file] = Object.fromEntries(
            Object.entries(baseline[file])
                .map(([identity, count]) => [
                    identity,
                    Math.min(count, current[identity] ?? 0),
                ])
                .filter(([, count]) => count > 0),
        );
    });

    write(baseline);
}

/**
 * Records the untagged tests of moved specs. The growth check rejects identities that are new.
 */
function add(files) {
    const baseline = readCurrent();

    files.forEach((file) => {
        baseline[baselineKey(BASELINE, resolve(file))] = untaggedTests(file);
    });

    write(baseline);
}

const [command, ...files] = process.argv.slice(2);
const commands = { init, prune, add: () => add(files) };

if (!commands[command] || (command === 'add' && files.length === 0)) {
    process.stderr.write('usage: baseline.js init | prune | add <spec>...\n');
    process.exit(2);
}

commands[command]();
