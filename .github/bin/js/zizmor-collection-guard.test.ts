import assert from 'node:assert/strict';
import { test } from 'node:test';
import { diffAgainstKnown, formatReport, parseSkippedFiles } from './zizmor-collection-guard.ts';


const warning = (path: string): string =>
    ` WARN zizmor::registry::input: failed to validate file://${path} as workflow: input does not match expected validation schema`;

test('parseSkippedFiles finds nothing in a clean run', () => {
    const log = ' INFO audit: zizmor: 🌈 completed ./.github/workflows/php.yml\n';

    assert.deepEqual(parseSkippedFiles(log), []);
});

test('parseSkippedFiles extracts and normalises every skipped path', () => {
    const log = [
        warning('./.github/workflows/acceptance.yml'),
        ' INFO audit: zizmor: 🌈 completed ./.github/workflows/php.yml',
        warning('.github/workflows/integration.yml'),
    ].join('\n');

    assert.deepEqual(parseSkippedFiles(log), [
        '.github/workflows/acceptance.yml',
        '.github/workflows/integration.yml',
    ]);
});

test('parseSkippedFiles reports each file once even when zizmor warns repeatedly', () => {
    const log = [warning('./.github/workflows/acceptance.yml'), warning('./.github/workflows/acceptance.yml')].join('\n');

    assert.deepEqual(parseSkippedFiles(log), ['.github/workflows/acceptance.yml']);
});

test('parseSkippedFiles ignores warnings that are not schema failures', () => {
    const log = ' WARN zizmor::registry::input: failed to validate file://./.github/workflows/php.yml as workflow: some other problem';

    assert.deepEqual(parseSkippedFiles(log), []);
});

test('diffAgainstKnown accepts a run that skips exactly the known files', () => {
    const known = ['.github/workflows/acceptance.yml'];

    assert.deepEqual(diffAgainstKnown(['.github/workflows/acceptance.yml'], known), { unexpected: [], stale: [] });
});

test('diffAgainstKnown reports a newly unparseable file as a coverage hole', () => {
    const known = ['.github/workflows/acceptance.yml'];
    const skipped = ['.github/workflows/acceptance.yml', '.github/workflows/php.yml'];

    assert.deepEqual(diffAgainstKnown(skipped, known), {
        unexpected: ['.github/workflows/php.yml'],
        stale: [],
    });
});

test('diffAgainstKnown reports a listed file that parses again as stale', () => {
    const known = ['.github/workflows/acceptance.yml', '.github/workflows/integration.yml'];

    assert.deepEqual(diffAgainstKnown(['.github/workflows/acceptance.yml'], known), {
        unexpected: [],
        stale: ['.github/workflows/integration.yml'],
    });
});

test('formatReport names the unaudited file and points at the allow list', () => {
    const report = formatReport({ unexpected: ['.github/workflows/php.yml'], stale: [] });

    assert.match(report, /NOT audited/);
    assert.match(report, /\.github\/workflows\/php\.yml/);
    assert.match(report, /KNOWN_UNCOLLECTABLE/);
});

test('formatReport explains a stale entry separately', () => {
    const report = formatReport({ unexpected: [], stale: ['.github/workflows/integration.yml'] });

    assert.match(report, /stale and should be removed/);
    assert.doesNotMatch(report, /NOT audited/);
});
