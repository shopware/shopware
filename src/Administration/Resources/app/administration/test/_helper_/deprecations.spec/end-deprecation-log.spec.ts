/**
 * @sw-package framework
 */

import { deprecations, startDeprecationLog } from '../deprecations';
import oldFunction from '../_fixtures/deprecations/oldFunction';
import { failureOfEndDeprecationLog, oldFunctionMessage, triggerOtherDeprecation } from './deprecations-test-setup';

describe('test/_helper_/deprecations - endDeprecationLog', () => {
    it('passes when the test asserted every deprecation', () => {
        oldFunction();

        expect(deprecations).toHaveTriggered(oldFunctionMessage);
        expect(failureOfEndDeprecationLog()).toBe('');
    });

    it('lists each unasserted deprecation', () => {
        oldFunction();
        triggerOtherDeprecation();

        expect(failureOfEndDeprecationLog()).toBe(
            [
                'The test ran into deprecations it did not assert. Fix each usage, or assert it with',
                '`expect(deprecations).toHaveTriggered(message)` from `test/_helper_/deprecations`.',
                '',
                '1. oldFunction() is deprecated.',
                '',
                '2. otherFunction() is deprecated.',
            ].join('\n'),
        );
    });

    it('clears the log', () => {
        oldFunction();
        failureOfEndDeprecationLog();
        startDeprecationLog();

        expect(deprecations).not.toHaveTriggered(oldFunctionMessage);
    });
});
