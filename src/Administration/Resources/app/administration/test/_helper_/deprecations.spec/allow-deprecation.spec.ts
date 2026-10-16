/**
 * @sw-package framework
 */

import { allowDeprecation } from '../deprecations';
import oldFunction from '../_fixtures/deprecations/oldFunction';
import {
    failureOfEndDeprecationLog,
    oldFunctionMessage,
    startNextTest,
    triggerOtherDeprecation,
} from './deprecations-test-setup';

describe('test/_helper_/deprecations - allowDeprecation', () => {
    it('lets a matching deprecation pass without an assertion', () => {
        allowDeprecation('oldFunction()');

        oldFunction();

        expect(failureOfEndDeprecationLog()).toBe('');
    });

    it('leaves other deprecations failing', () => {
        allowDeprecation('oldFunction()');

        oldFunction();
        triggerOtherDeprecation();

        const failure = failureOfEndDeprecationLog();
        expect(failure).toContain('1. otherFunction() is deprecated.');
        expect(failure).not.toContain(oldFunctionMessage);
    });

    it('is cleared when the test ends', () => {
        allowDeprecation('oldFunction()');
        startNextTest();

        oldFunction();

        expect(failureOfEndDeprecationLog()).toContain(oldFunctionMessage);
    });

    describe('across two tests', () => {
        it('allows the deprecation in the first test', () => {
            allowDeprecation('oldFunction()');

            oldFunction();

            expect(failureOfEndDeprecationLog()).not.toContain(oldFunctionMessage);
        });

        it('fails the second test that runs into it without an allowance', () => {
            oldFunction();

            expect(failureOfEndDeprecationLog()).toContain(oldFunctionMessage);
        });
    });
});
