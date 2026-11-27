/**
 * @sw-package framework
 */

import Feature from 'src/core/feature';
import { endDeprecationLog, startDeprecationLog } from '../deprecations';

// Registers the stand-in major of `oldFunction()`, so the guard does not report it as a typo.
Feature.init({ V7_0_0_0: false });

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
    return messageThrownBy(endDeprecationLog);
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

/**
 * Pins the run to the normal baseline check, not update mode, even when the suite runs with
 * `DEPRECATION_BASELINE=update`.
 *
 * @private
 * @example
 * beforeEach(ignoreBaselineUpdateRun);
 */
export function ignoreBaselineUpdateRun(): void {
    jest.replaceProperty(process, 'env', { ...process.env, DEPRECATION_BASELINE: undefined });
}

/**
 * Pins the run to `DEPRECATION_BASELINE=update`, where every deprecation passes and the run collects used
 * baseline entries. Use it for cases that cover update mode.
 *
 * @private
 * @example
 * useBaselineUpdateRun();
 */
export function useBaselineUpdateRun(): void {
    jest.replaceProperty(process, 'env', { ...process.env, DEPRECATION_BASELINE: 'update' });
}

/**
 * Makes the current test look like a spec at `testPath`, for the cases that depend on where the spec lives, see
 * `isPlatformDeprecationRun()`. Jest restores the real path before the next test.
 *
 * @private
 * @example
 * useSpecPath('/var/www/html/custom/plugins/SwagExample/src/Resources/app/administration/src/example.spec.ts');
 */
export function useSpecPath(testPath: string | undefined): void {
    jest.replaceProperty(expect.getState(), 'testPath', testPath);
}
