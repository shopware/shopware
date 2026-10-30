/**
 * @sw-package framework
 */

import { allowDeprecation } from '../deprecations';
import oldFunction from '../_fixtures/deprecations/oldFunction';
import {
    failureOfEndDeprecationLog,
    ignoreBaselineUpdateRun,
    messageThrownBy,
    oldFunctionMessage,
    startNextTest,
    triggerOtherDeprecation,
} from './deprecations-test-setup';

const outsideTestMessage =
    'allowDeprecation() only works inside a test or its beforeEach: the setup resets it before every test.';

describe('test/_helper_/deprecations - allowDeprecation', () => {
    beforeEach(ignoreBaselineUpdateRun);

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

    describe('in a beforeEach', () => {
        beforeEach(() => {
            allowDeprecation('oldFunction()');
        });

        it('lets a matching deprecation pass', () => {
            oldFunction();

            expect(failureOfEndDeprecationLog()).toBe('');
        });
    });

    describe('outside a test', () => {
        const failureInDescribeBody = messageThrownBy(() => allowDeprecation('oldFunction()'));
        let failureInBeforeAll = '';

        beforeAll(() => {
            failureInBeforeAll = messageThrownBy(() => allowDeprecation('oldFunction()'));
        });

        it('throws in a describe body', () => {
            expect(failureInDescribeBody).toBe(outsideTestMessage);
        });

        it('throws in a beforeAll', () => {
            expect(failureInBeforeAll).toBe(outsideTestMessage);
        });
    });
});
