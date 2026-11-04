/**
 * @sw-package framework
 */

import { baselineDeprecation, currentRun, isBaselined, resetBaseline, type DeprecationRun } from './deprecationBaseline';
import { allowDeprecation, deprecations, endDeprecationLog } from './deprecations';
import {
    failureOfEndDeprecationLog,
    ignoreBaselineUpdateRun,
    oldFunctionMessage,
    useBaselineUpdateRun,
} from './deprecations.spec/deprecations-test-setup';
import {
    startCollectingUsedBaselineEntries,
    stopCollectingUsedBaselineEntries,
    usedBaselineEntries,
} from './usedBaselineEntries';
import oldFunction from './_fixtures/deprecations/oldFunction';

const message = oldFunctionMessage;
const spec = 'test/_helper_/deprecationBaseline.spec.ts';
const otherRun: DeprecationRun = currentRun() === 'major' ? 'default' : 'major';

describe('test/_helper_/deprecationBaseline', () => {
    beforeEach(() => {
        resetBaseline();
        // Keeps the used entries of these specs away from the run's reporter, and its update mode away from these specs.
        ignoreBaselineUpdateRun();
        startCollectingUsedBaselineEntries();
    });

    // The setup's `afterEach` records the used entries of a test after the hooks of this file ran.
    afterAll(stopCollectingUsedBaselineEntries);

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
        it('counts a listed usage the test did not assert as used, once', () => {
            baselineDeprecation({ message, callSites: { [spec]: [currentRun()] } });

            oldFunction();
            oldFunction();
            endDeprecationLog();

            expect(usedBaselineEntries()).toEqual([[message, spec]]);
        });

        it('counts a listed usage the test asserted as used', () => {
            baselineDeprecation({ message, callSites: { [spec]: [currentRun()] } });

            oldFunction();
            expect(deprecations).toHaveTriggered(message);
            endDeprecationLog();

            expect(usedBaselineEntries()).toEqual([[message, spec]]);
        });

        it('counts a listed usage the test allowed as used', () => {
            baselineDeprecation({ message, callSites: { [spec]: [currentRun()] } });
            allowDeprecation(message);

            oldFunction();
            endDeprecationLog();

            expect(usedBaselineEntries()).toEqual([[message, spec]]);
        });

        it('counts a listed usage the deprecation plugin appended its component path to under its baseline key', () => {
            baselineDeprecation({ message, callSites: { [spec]: [currentRun()] } });

            Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', `${message}\nUsed in: sw-app > sw-page`);
            endDeprecationLog();

            expect(usedBaselineEntries()).toEqual([[message, spec]]);
        });

        it('does not count an entry listed only for the other run as used, even when the test asserts it', () => {
            baselineDeprecation({ message, callSites: { [spec]: [otherRun] } });

            oldFunction();
            expect(deprecations).toHaveTriggered(message);
            endDeprecationLog();

            expect(usedBaselineEntries()).toEqual([]);
        });

        it('lets every usage pass with DEPRECATION_BASELINE=update, counting the ones the test neither asserted nor allowed as used', () => {
            useBaselineUpdateRun();
            allowDeprecation('allowed()');

            oldFunction();
            Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'asserted() is deprecated.');
            Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'allowed() is deprecated.');
            expect(deprecations).toHaveTriggered('asserted()');

            expect(failureOfEndDeprecationLog()).toBe('');
            expect(usedBaselineEntries()).toEqual([[message, spec]]);
        });

        it('still counts a listed usage the test asserted as used with DEPRECATION_BASELINE=update', () => {
            useBaselineUpdateRun();
            baselineDeprecation({ message, callSites: { [spec]: [currentRun()] } });

            oldFunction();
            expect(deprecations).toHaveTriggered(message);
            endDeprecationLog();

            expect(usedBaselineEntries()).toEqual([[message, spec]]);
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

    describe('currentRun', () => {
        it('is major when the environment activates the major flag', () => {
            jest.replaceProperty(process, 'env', { ...process.env, V6_8_0_0: '1' });

            expect(currentRun()).toBe('major');
        });

        it('is default when the environment activates no major flag', () => {
            jest.replaceProperty(process, 'env', { ...process.env, V6_8_0_0: undefined, FEATURE_ALL: undefined });

            expect(currentRun()).toBe('default');
        });
    });
});
