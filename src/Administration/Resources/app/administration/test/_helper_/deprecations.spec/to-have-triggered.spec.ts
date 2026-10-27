/**
 * @sw-package framework
 */

import { deprecations } from '../deprecations';
import oldFunction from '../_fixtures/deprecations/oldFunction';
import {
    failureOfEndDeprecationLog,
    ignoreBaselineUpdateRun,
    oldFunctionMessage,
    triggerOtherDeprecation,
} from './deprecations-test-setup';

describe('test/_helper_/deprecations - toHaveTriggered', () => {
    beforeEach(ignoreBaselineUpdateRun);

    it('passes for a deprecation the test ran into', () => {
        oldFunction();

        expect(deprecations).toHaveTriggered(oldFunctionMessage);
    });

    it('matches a string as part of the message', () => {
        oldFunction();

        expect(deprecations).toHaveTriggered('oldFunction()');
    });

    it('matches a regular expression against the message', () => {
        oldFunction();

        expect(deprecations).toHaveTriggered(/^oldFunction\(\)/);
    });

    it('covers every occurrence of the one deprecation it matches', () => {
        oldFunction();
        oldFunction();

        expect(deprecations).toHaveTriggered(/oldFunction\(\) is deprecated/g);

        expect(failureOfEndDeprecationLog()).toBe('');
    });

    it('fails at the assertion for a deprecation that did not fire', () => {
        oldFunction();

        expect(() => {
            expect(deprecations).toHaveTriggered('neverDeprecated()');
        }).toThrow('Expected a deprecation matching: "neverDeprecated()"');
        expect(deprecations).toHaveTriggered(oldFunctionMessage);
    });

    it('lists what was recorded when the assertion fails', () => {
        oldFunction();

        expect(() => {
            expect(deprecations).toHaveTriggered('neverDeprecated()');
        }).toThrow('Deprecations recorded in this test:\n  "oldFunction() is deprecated."');
        expect(deprecations).toHaveTriggered(oldFunctionMessage);
    });

    it('says so when the test recorded no deprecation', () => {
        expect(() => expect(deprecations).toHaveTriggered('neverDeprecated()')).toThrow(
            'Deprecations recorded in this test: none',
        );
    });

    it('passes `.not` while no matching deprecation fired', () => {
        expect(deprecations).not.toHaveTriggered(oldFunctionMessage);
    });

    it('fails `.not` once a matching deprecation fired', () => {
        oldFunction();

        expect(() => expect(deprecations).not.toHaveTriggered('oldFunction()')).toThrow(
            'Expected no deprecation matching: "oldFunction()"',
        );
    });

    it('fails `.not` for a string that matches several deprecations', () => {
        oldFunction();
        triggerOtherDeprecation();

        expect(() => expect(deprecations).not.toHaveTriggered('is deprecated.')).toThrow(
            'Expected no deprecation matching: "is deprecated."',
        );
    });

    it('lists what was recorded when `.not` fails', () => {
        oldFunction();

        expect(() => expect(deprecations).not.toHaveTriggered('oldFunction()')).toThrow(
            'Deprecations recorded in this test:\n  "oldFunction() is deprecated."',
        );
    });

    it('covers only the deprecations it matches', () => {
        oldFunction();
        triggerOtherDeprecation();

        expect(deprecations).toHaveTriggered(oldFunctionMessage);

        const failure = failureOfEndDeprecationLog();
        expect(failure).toContain('1. otherFunction() is deprecated.');
        expect(failure).not.toContain(oldFunctionMessage);
    });

    it('fails for a string that matches several deprecations and covers none of them', () => {
        oldFunction();
        triggerOtherDeprecation();

        expect(() => {
            expect(deprecations).toHaveTriggered('is deprecated.');
        }).toThrow(
            'Expected one deprecation matching "is deprecated.", but it matches 2:\n  "oldFunction() is deprecated."\n  "otherFunction() is deprecated."\nUse a more specific message.',
        );

        const failure = failureOfEndDeprecationLog();
        expect(failure).toContain('1. oldFunction() is deprecated.');
        expect(failure).toContain('2. otherFunction() is deprecated.');
    });

    it('accepts only the `deprecations` handle', () => {
        // @ts-expect-error the matcher type-checks only on the handle
        expect(() => expect({}).toHaveTriggered('oldFunction()')).toThrow(
            'value must be `deprecations` from test/_helper_/deprecations',
        );
    });
});
