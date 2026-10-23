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
import { deprecations } from './deprecations';
import { failureOfEndDeprecationLog, oldFunctionMessage } from './deprecations.spec/deprecations-test-setup';
import oldFunction from './_fixtures/deprecations/oldFunction';

const message = oldFunctionMessage;
const spec = 'test/_helper_/deprecationBaseline.spec.ts';
const otherRun: DeprecationRun = currentRun() === 'major' ? 'default' : 'major';

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
