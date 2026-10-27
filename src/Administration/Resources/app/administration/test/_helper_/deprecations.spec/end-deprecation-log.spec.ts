/**
 * @sw-package framework
 */

import { deprecations, endDeprecationLog, startDeprecationLog } from '../deprecations';
import oldFunction from '../_fixtures/deprecations/oldFunction';
import {
    failureOfEndDeprecationLog,
    ignoreBaselineUpdateRun,
    oldFunctionMessage,
    triggerOtherDeprecation,
} from './deprecations-test-setup';

/**
 * `report` with every `:line:column` replaced, so the assertion survives edits that move code.
 *
 * @example
 * withoutLineNumbers('↳ test/oldFunction.ts:13:13'); // '↳ test/oldFunction.ts:<line>:<col>'
 */
function withoutLineNumbers(report: string): string {
    return report.replace(/:\d+:\d+/g, ':<line>:<col>');
}

describe('test/_helper_/deprecations - endDeprecationLog', () => {
    beforeEach(ignoreBaselineUpdateRun);

    it('passes when the test asserted every deprecation', () => {
        oldFunction();

        expect(deprecations).toHaveTriggered(oldFunctionMessage);
        expect(failureOfEndDeprecationLog()).toBe('');
    });

    it('lists each unasserted deprecation with where it is used and the lines that led to it', () => {
        oldFunction();
        triggerOtherDeprecation();

        expect(withoutLineNumbers(failureOfEndDeprecationLog())).toBe(
            [
                'The test ran into deprecations it did not assert. Fix each usage, or assert it with',
                '`expect(deprecations).toHaveTriggered(message)` from `test/_helper_/deprecations`.',
                '',
                '1. oldFunction() is deprecated.',
                '   used in test/_helper_/deprecations.spec/end-deprecation-log.spec.ts',
                '   ↳ test/_helper_/_fixtures/deprecations/oldFunction.ts:<line>:<col>',
                '   ↳ test/_helper_/deprecations.spec/end-deprecation-log.spec.ts:<line>:<col>',
                '',
                '2. otherFunction() is deprecated.',
                '   used in test/_helper_/deprecations.spec/end-deprecation-log.spec.ts',
                '   ↳ test/_helper_/deprecations.spec/deprecations-test-setup.ts:<line>:<col>',
                '   ↳ test/_helper_/deprecations.spec/end-deprecation-log.spec.ts:<line>:<col>',
            ].join('\n'),
        );
    });

    it('points the stack of the failure at the first unasserted usage', () => {
        oldFunction();
        triggerOtherDeprecation();

        let failure = new Error();
        try {
            endDeprecationLog();
        } catch (error) {
            failure = error as Error;
        }
        startDeprecationLog();

        const lines = (failure.stack ?? '').split('\n');
        expect(lines[0]).toMatch(/^Error: The test ran into deprecations/);
        expect(lines.find((line) => /^\s+at /.test(line))).toContain('test/_helper_/_fixtures/deprecations/oldFunction.ts:');
        expect(lines.filter((line) => line.includes('test/_helper_/deprecations.ts'))).toEqual([]);
    });

    it('clears the log', () => {
        oldFunction();
        failureOfEndDeprecationLog();
        startDeprecationLog();

        expect(deprecations).not.toHaveTriggered(oldFunctionMessage);
    });
});
