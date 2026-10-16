/**
 * @sw-package framework
 */

import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import {
    baselineDeprecation,
    currentRun,
    deprecationBaselineKey,
    isBaselined,
    resetBaseline,
    type DeprecationRun,
} from './deprecationBaseline';
import { allowDeprecation, deprecations, endDeprecationLog } from './deprecations';
import oldFunction from './_fixtures/deprecations/oldFunction';

const message = 'oldFunction() is deprecated.';
const spec = 'test/_helper_/deprecationBaseline.spec.ts';
const otherRun: DeprecationRun = currentRun() === 'major' ? 'default' : 'major';

/**
 * Ends the current test's log the way the setup's `afterEach` does, and returns the failure it reported, or an
 * empty string.
 *
 * @example
 * expect(failureOfEndDeprecationLog()).toBe('');
 */
function failureOfEndDeprecationLog(): string {
    try {
        endDeprecationLog();

        return '';
    } catch (error) {
        return (error as Error).message;
    }
}

describe('test/_helper_/deprecationBaseline', () => {
    let hitsDirectory: string;
    const hitsDirectories: string[] = [];

    beforeEach(() => {
        resetBaseline();
        // Keeps the hits of these specs away from the run's reporter, and its update mode away from these specs.
        hitsDirectory = mkdtempSync(join(tmpdir(), 'deprecation-baseline-spec-'));
        hitsDirectories.push(hitsDirectory);
        process.env.DEPRECATION_BASELINE_HITS = hitsDirectory;
        delete process.env.DEPRECATION_BASELINE;
    });

    // The setup's `afterEach` records the hits of a test after the hooks of this file ran.
    afterAll(() => {
        hitsDirectories.forEach((directory) => rmSync(directory, { recursive: true, force: true }));
    });

    /**
     * The `[message, usage site]` pairs this worker recorded for the reporter during the test.
     *
     * @example
     * expect(recordedHits()).toEqual([['oldFunction() is deprecated.', spec]]);
     */
    function recordedHits(): string[][] {
        const hitsFile = join(hitsDirectory, `${process.pid}.jsonl`);

        if (!existsSync(hitsFile)) {
            return [];
        }

        return readFileSync(hitsFile, 'utf8')
            .trim()
            .split('\n')
            .map((line) => JSON.parse(line) as string[]);
    }

    describe('in a test', () => {
        it('lets a usage listed for the current run pass without an assertion', () => {
            baselineDeprecation({ message, callSites: { [spec]: [currentRun()] } });

            oldFunction();

            expect(failureOfEndDeprecationLog()).toBe('');
        });

        it('still lets the test assert a listed usage', () => {
            baselineDeprecation({ message, callSites: { [spec]: ['default', 'major'] } });

            oldFunction();

            expect(deprecations).toHaveTriggered(message);
        });

        it('fails the test for a usage listed only for the other run', () => {
            baselineDeprecation({ message, callSites: { [spec]: [otherRun] } });

            oldFunction();

            expect(failureOfEndDeprecationLog()).toContain(`1. ${message}\n   used in ${spec}`);
        });

        it('fails the test for a usage at a site the baseline does not list', () => {
            baselineDeprecation({ message, callSites: { 'sw-page > sw-old': ['default', 'major'] } });

            oldFunction();

            expect(failureOfEndDeprecationLog()).toContain(`1. ${message}\n   used in ${spec}`);
        });
    });

    describe('for the reporter', () => {
        it('records a listed usage the test did not assert, once', () => {
            baselineDeprecation({ message, callSites: { [spec]: [currentRun()] } });

            oldFunction();
            oldFunction();
            endDeprecationLog();

            expect(recordedHits()).toEqual([[message, spec]]);
        });

        it('records a listed usage the test asserted', () => {
            baselineDeprecation({ message, callSites: { [spec]: [currentRun()] } });

            oldFunction();
            expect(deprecations).toHaveTriggered(message);
            endDeprecationLog();

            expect(recordedHits()).toEqual([[message, spec]]);
        });

        it('records a listed usage the test allowed', () => {
            baselineDeprecation({ message, callSites: { [spec]: [currentRun()] } });
            allowDeprecation(message);

            oldFunction();
            endDeprecationLog();

            expect(recordedHits()).toEqual([[message, spec]]);
        });

        it('does not record a usage listed only for the other run', () => {
            baselineDeprecation({ message, callSites: { [spec]: [otherRun] } });

            oldFunction();
            expect(deprecations).toHaveTriggered(message);
            endDeprecationLog();

            expect(recordedHits()).toEqual([]);
        });

        it('lets every usage pass and records it with DEPRECATION_BASELINE=update, except asserted and allowed ones', () => {
            process.env.DEPRECATION_BASELINE = 'update';
            allowDeprecation('allowed()');

            oldFunction();
            Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'asserted() is deprecated.');
            Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'allowed() is deprecated.');
            expect(deprecations).toHaveTriggered('asserted()');

            expect(failureOfEndDeprecationLog()).toBe('');
            expect(recordedHits()).toEqual([[message, spec]]);
        });

        it('keeps recording a listed usage the test asserted with DEPRECATION_BASELINE=update', () => {
            process.env.DEPRECATION_BASELINE = 'update';
            baselineDeprecation({ message, callSites: { [spec]: [currentRun()] } });

            oldFunction();
            expect(deprecations).toHaveTriggered(message);
            endDeprecationLog();

            expect(recordedHits()).toEqual([[message, spec]]);
        });
    });

    describe('isBaselined', () => {
        it('matches a message the deprecation plugin appended its component path to', () => {
            baselineDeprecation({ message, callSites: { 'sw-page > sw-old': ['default', 'major'] } });

            expect(
                isBaselined({ message: `${message}\nUsed in: sw-app > sw-page > sw-old`, usageSite: 'sw-page > sw-old' }),
            ).toBe(true);
        });

        it('lists nothing after resetBaseline', () => {
            baselineDeprecation({ message, callSites: { [spec]: ['default', 'major'] } });

            resetBaseline();

            expect(isBaselined({ message, usageSite: spec })).toBe(false);
        });
    });

    describe('deprecationBaselineKey', () => {
        it('drops the component path the deprecation plugin appends', () => {
            expect(deprecationBaselineKey('The component "sw-x" is deprecated.\nUsed in: sw-page > sw-x')).toBe(
                'The component "sw-x" is deprecated.',
            );
        });
    });

    describe('currentRun', () => {
        // Jest gives each spec file its own copy of `process.env`, so the changes stay in this file.
        const runEnvironment = { ...process.env };

        afterEach(() => {
            process.env = { ...runEnvironment };
        });

        it('is major when the environment activates the major flag', () => {
            process.env.V6_8_0_0 = '1';

            expect(currentRun()).toBe('major');
        });

        it('is default when the environment activates no major flag', () => {
            delete process.env.V6_8_0_0;
            delete process.env.FEATURE_ALL;

            expect(currentRun()).toBe('default');
        });
    });
});
