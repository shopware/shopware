/**
 * @sw-package framework
 */

import {
    baselineDeprecation,
    currentRun,
    deprecationBaselineKey,
    isBaselined,
    resetBaseline,
    type DeprecationRun,
} from './deprecationBaseline';
import { deprecations, endDeprecationLog } from './deprecations';
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
    beforeEach(resetBaseline);

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
