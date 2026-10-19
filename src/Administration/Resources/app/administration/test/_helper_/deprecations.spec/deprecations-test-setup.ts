/**
 * @sw-package framework
 */

import { endDeprecationLog, startDeprecationLog } from '../deprecations';

/**
 * The message of the `oldFunction()` fixture.
 *
 * @private
 */
export const oldFunctionMessage = 'oldFunction() is deprecated.';

/**
 * A second deprecation next to `oldFunction()`, through `Shopware.Feature` and an upcoming major that only warns
 * in production.
 *
 * @private
 */
export function triggerOtherDeprecation(): void {
    Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'otherFunction() is deprecated.');
}

/**
 * @returns the error message of `endDeprecationLog` instead of throwing. `endDeprecationLog` throws for all triggered deprecations that were neither asserted nor allowed.
 *
 * @private
 * @example
 * const failure = failureOfEndDeprecationLog();
 * expect(failure).toContain('1. oldFunction() is deprecated.');
 * expect(failure).not.toContain('otherFunction() is deprecated.');
 */
export function failureOfEndDeprecationLog(): string {
    try {
        endDeprecationLog();

        return '';
    } catch (error) {
        return (error as Error).message;
    }
}

/**
 * @returns the message `callback` throws, or `''` when it returns, so a `beforeAll` or a `describe` body can keep
 * an error for a test to assert.
 *
 * @private
 * @example
 * const failure = messageThrownBy(() => allowDeprecation('oldFunction()'));
 */
export function messageThrownBy(callback: () => void): string {
    try {
        callback();

        return '';
    } catch (error) {
        return (error as Error).message;
    }
}

/**
 * Ends the current test's log the way the setup's hooks do between two tests, and starts the next one.
 *
 * @private
 * @example
 * allowDeprecation('oldFunction()');
 * startNextTest();
 */
export function startNextTest(): void {
    endDeprecationLog();
    startDeprecationLog();
}
