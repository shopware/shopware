/**
 * @sw-package framework
 */

import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import DeprecationBaselineReporter from './deprecationBaselineReporter';
import type { DeprecationRun } from './deprecationBaseline';
import { ignoreBaselineUpdateRun, useBaselineUpdateRun } from './deprecations.spec/deprecations-test-setup';
import { recordUsedBaselineEntry } from './usedBaselineEntries';

const fullRun = {
    nonFlagArgs: [],
    testPathPatterns: { isSet: () => false },
    testNamePattern: '',
    onlyChanged: false,
    onlyFailures: false,
    shard: undefined,
};

const greenRun = { numFailedTests: 0, numFailedTestSuites: 0, runExecError: undefined, wasInterrupted: false };

const message = 'oldFunction() is deprecated.';

/**
 * The baseline each test starts from.
 */
const baselineBeforeTheRun = { [message]: { 'sw-author': ['default', 'major'], 'sw-page': ['major'] } };

const baselineDirectory = mkdtempSync(join(tmpdir(), 'deprecation-baseline-spec-'));
const baselinePath = join(baselineDirectory, 'baseline.json');

/**
 * Replaces the baseline file the reporter reads.
 *
 * @example
 * writeBaseline({ 'old() is deprecated.': { 'sw-page': ['major'] } });
 */
function writeBaseline(baseline: object): void {
    writeFileSync(baselinePath, JSON.stringify(baseline));
}

/**
 * The baseline file the reporter wrote.
 *
 * @example
 * expect(readBaseline()).toEqual({});
 */
function readBaseline(): unknown {
    return JSON.parse(readFileSync(baselinePath, 'utf8'));
}

/**
 * Runs the reporter as Jest would, with the workers having recorded `usedEntries` as `[message, site]`, and `argv`
 * as the command line options. Returns the error the reporter reported, if any.
 *
 * @example
 * expect(run([[message, 'sw-page']], { run: 'major' })).toBeUndefined();
 */
function run(
    usedEntries: string[][],
    {
        globalConfig = fullRun as object,
        run: jestRun = 'default' as DeprecationRun,
        results = greenRun as object,
        argv = [] as string[],
    } = {},
): Error | undefined {
    jest.replaceProperty(process, 'argv', ['node', 'jest', ...argv]);
    const reporter = new DeprecationBaselineReporter(globalConfig, { baselinePath, run: jestRun });

    reporter.onRunStart();
    usedEntries.forEach(([usedMessage, usageSite]) => recordUsedBaselineEntry({ message: usedMessage, usageSite }));
    reporter.onRunComplete(new Set(), results);

    return reporter.getLastError();
}

describe('test/_helper_/deprecationBaselineReporter', () => {
    let stderrWrite: jest.SpyInstance;

    beforeEach(() => {
        ignoreBaselineUpdateRun();
        writeBaseline(baselineBeforeTheRun);
        jest.spyOn(console, 'error').mockImplementation(() => {});
        stderrWrite = jest.spyOn(process.stderr, 'write').mockImplementation(() => true);
    });

    afterAll(() => {
        rmSync(baselineDirectory, { recursive: true, force: true });
    });

    describe('stale check', () => {
        it('passes a full run that needed every usage listed for it, ignoring those listed for the other run', () => {
            expect(run([[message, 'sw-author']], { run: 'default' })).toBeUndefined();
        });

        it('prints how many usages a full run checked', () => {
            run(
                [
                    [message, 'sw-author'],
                    [message, 'sw-page'],
                ],
                { run: 'major' },
            );

            expect(stderrWrite).toHaveBeenCalledWith(
                'Deprecation baseline: checked the 2 usages listed for the major run.\n',
            );
        });

        it('fails a full run on a usage listed for it that no spec needed', () => {
            const error = run([[message, 'sw-author']], { run: 'major' });

            expect(error?.message).toContain('no spec of the major run needs anymore');
            expect(error?.message).toContain(`sw-page: ${message}`);
            expect(console.error).toHaveBeenCalledWith(expect.stringContaining(`sw-page: ${message}`));
        });

        it.each([
            ['default' as const, 'composer admin:unit'],
            ['major' as const, 'composer admin:unit:major'],
        ])('names the update command of the %s run on a stale usage', (jestRun, command) => {
            expect(run([], { run: jestRun })?.message).toContain(`DEPRECATION_BASELINE=update ${command}\n`);
        });

        it.each([
            ['a path argument', { nonFlagArgs: ['src/app'] }],
            ['--testPathPatterns', { testPathPatterns: { isSet: () => true } }],
            ['-t', { testNamePattern: 'renders' }],
            ['--onlyChanged, --changedSince or --lastCommit', { onlyChanged: true }],
            ['--onlyFailures', { onlyFailures: true }],
            ['--shard', { shard: { shardIndex: 1, shardCount: 2 } }],
            ['--testPathIgnorePatterns', {}, ['--testPathIgnorePatterns', 'sw-order']],
            ['--test-path-ignore-patterns=', {}, ['--test-path-ignore-patterns=sw-order']],
            ['--selectProjects', {}, ['--selectProjects', 'administration']],
        ])('does not report stale usages of a run filtered by %s', (_filter, override, argv: string[] = []) => {
            expect(run([], { globalConfig: { ...fullRun, ...override }, argv })).toBeUndefined();
        });

        it('prints in CI that it skipped the check of a filtered run', () => {
            jest.replaceProperty(process, 'env', { ...process.env, CI: 'true' });

            run([], { globalConfig: { ...fullRun, onlyChanged: true } });

            expect(stderrWrite).toHaveBeenCalledWith(
                'Deprecation baseline: skipped the stale check, because the run is filtered.\n',
            );
        });

        it('stays quiet outside CI on a filtered run', () => {
            jest.replaceProperty(process, 'env', { ...process.env, CI: undefined });

            run([], { globalConfig: { ...fullRun, onlyChanged: true } });

            expect(stderrWrite).not.toHaveBeenCalled();
        });

        it('does not report stale usages of a run with a failed or crashed spec', () => {
            expect(run([], { results: { ...greenRun, numFailedTestSuites: 1 } })).toBeUndefined();
        });
    });

    describe('with DEPRECATION_BASELINE=update', () => {
        beforeEach(() => {
            useBaselineUpdateRun();
        });

        it('marks the run on exactly the usages it needed, keeping the markings of the other run', () => {
            expect(run([[message, 'sw-page']], { run: 'default' })).toBeUndefined();

            expect(readBaseline()).toEqual({ [message]: { 'sw-author': ['major'], 'sw-page': ['default', 'major'] } });
        });

        it('drops a usage and a message no run marks anymore', () => {
            writeBaseline({ [message]: { 'sw-page': ['major'] }, 'b() is deprecated.': { 'sw-page': ['major'] } });

            expect(run([[message, 'sw-author']], { run: 'major' })).toBeUndefined();

            expect(readBaseline()).toEqual({ [message]: { 'sw-author': ['major'] } });
        });

        it('writes messages, sites and runs sorted', () => {
            writeBaseline({ 'b() is deprecated.': { 'sw-page': ['major'] } });

            expect(
                run(
                    [
                        ['b() is deprecated.', 'sw-page'],
                        ['a() is deprecated.', 'sw-page'],
                        ['b() is deprecated.', 'sw-author'],
                        ['b() is deprecated.', 'sw-page'],
                    ],
                    { run: 'default' },
                ),
            ).toBeUndefined();

            expect(readFileSync(baselinePath, 'utf8')).toBe(
                `${JSON.stringify(
                    {
                        'a() is deprecated.': { 'sw-page': ['default'] },
                        'b() is deprecated.': { 'sw-author': ['default'], 'sw-page': ['default', 'major'] },
                    },
                    null,
                    4,
                )}\n`,
            );
            expect(stderrWrite).toHaveBeenCalledWith('Deprecation baseline: marked the 3 usages of the default run.\n');
        });

        it.each([
            ['a failed test', { numFailedTests: 1, numFailedTestSuites: 1 }],
            ['a crashed suite', { numFailedTestSuites: 1 }],
            ['an error of Jest itself', { runExecError: { message: 'out of memory' } }],
            ['an interrupted run', { wasInterrupted: true }],
        ])('keeps the baseline after a run with %s', (_failure, override) => {
            const before = readFileSync(baselinePath, 'utf8');

            const error = run([['a() is deprecated.', 'sw-page']], { results: { ...greenRun, ...override } });

            expect(error?.message).toContain('fix the failing specs first');
            expect(readFileSync(baselinePath, 'utf8')).toBe(before);
        });

        it('refuses a filtered run and keeps the baseline', () => {
            const before = readFileSync(baselinePath, 'utf8');

            const error = run([], { globalConfig: { ...fullRun, onlyChanged: true } });

            expect(error?.message).toContain('needs a full run');
            expect(error?.message).toContain('DEPRECATION_BASELINE=update composer admin:unit');
            expect(console.error).toHaveBeenCalledWith(expect.stringContaining('needs a full run'));
            expect(readFileSync(baselinePath, 'utf8')).toBe(before);
        });
    });
});
