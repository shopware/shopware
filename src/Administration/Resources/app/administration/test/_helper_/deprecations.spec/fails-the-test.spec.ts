/**
 * @sw-package framework
 */

import { failsTheTest, type RecordedDeprecation } from '../deprecations';
import { ignoreBaselineUpdateRun, oldFunctionMessage } from './deprecations-test-setup';

describe('test/_helper_/deprecations - failsTheTest', () => {
    beforeEach(ignoreBaselineUpdateRun);

    const recorded: RecordedDeprecation = {
        message: oldFunctionMessage,
        error: new Error(),
        usageSite: 'sw-page',
        asserted: false,
    };

    it('fails for one neither asserted nor allowed', () => {
        expect(failsTheTest(recorded, [])).toBe(true);
    });

    it('passes once asserted', () => {
        expect(failsTheTest({ ...recorded, asserted: true }, [])).toBe(false);
    });

    it('passes once allowed', () => {
        expect(failsTheTest(recorded, ['oldFunction()'])).toBe(false);
    });
});
