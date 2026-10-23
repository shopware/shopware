/**
 * @sw-package framework
 */

import { deprecations, useProductionDeprecationGuard } from '../deprecations';
import { isPlatformDeprecationRun, unsetPlatformDeprecationRunMarker } from '../platformDeprecationRun';
import oldFunction from '../_fixtures/deprecations/oldFunction';
import {
    failureOfEndDeprecationLog,
    ignoreBaselineUpdateRun,
    oldFunctionMessage,
    triggerOtherDeprecation,
} from './deprecations-test-setup';

/**
 * Asserts that the recorder neither recorded nor reported anything in the current test.
 *
 * @example
 * expectSilence();
 */
function expectSilence(): void {
    expect(deprecations.entries).toEqual([]);
    expect(failureOfEndDeprecationLog()).toBe('');
    expect(console.warn).not.toHaveBeenCalled();
    expect(console.error).not.toHaveBeenCalled();
}

describe('test/_helper_/deprecations - platform run', () => {
    beforeEach(ignoreBaselineUpdateRun);

    describe('in Shopware’s own Jest run', () => {
        it('is a platform run', () => {
            expect(isPlatformDeprecationRun()).toBe(true);
        });

        it('records a deprecation', () => {
            oldFunction();

            expect(deprecations).toHaveTriggered(oldFunctionMessage);
        });
    });

    describe('in the Jest run of an extension', () => {
        beforeEach(() => {
            unsetPlatformDeprecationRunMarker();
            jest.spyOn(console, 'warn');
            jest.spyOn(console, 'error');
        });

        it('is no platform run', () => {
            expect(isPlatformDeprecationRun()).toBe(false);
        });

        it('neither records, reports nor fails on a deprecation', () => {
            triggerOtherDeprecation();
            oldFunction();

            expectSilence();
        });

        it.activeFeatureFlags(['v7.0.0.0'])('does not throw for a major the test activates', () => {
            expect(() => oldFunction()).not.toThrow();
            expectSilence();
        });

        it('does not reach the production guard', () => {
            useProductionDeprecationGuard();

            triggerOtherDeprecation();

            expectSilence();
        });
    });
});
