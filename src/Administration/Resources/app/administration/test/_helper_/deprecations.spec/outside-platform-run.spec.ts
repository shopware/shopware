/**
 * @sw-package framework
 */

import { resolve } from 'node:path';
import { allowDeprecation, deprecations, failsTheTest, useProductionDeprecationGuard } from '../deprecations';
import { isPlatformDeprecationRun } from '../platformDeprecationRun';
import oldFunction from '../_fixtures/deprecations/oldFunction';
import {
    failureOfEndDeprecationLog,
    ignoreBaselineUpdateRun,
    oldFunctionMessage,
    triggerOtherDeprecation,
    useSpecPath,
} from './deprecations-test-setup';

const repositoryPath = resolve(__dirname, '../../..', '../../../../..');
const extensionSpecPath = resolve(
    repositoryPath,
    'custom/plugins/SwagExample/src/Resources/app/administration/src/example.spec.ts',
);

describe('test/_helper_/deprecations - platform run', () => {
    beforeEach(ignoreBaselineUpdateRun);

    describe('isPlatformDeprecationRun', () => {
        it('counts a spec in the platform’s src/ as the platform’s run', () => {
            expect(isPlatformDeprecationRun()).toBe(true);

            useSpecPath(resolve(repositoryPath, 'src/Storefront/Resources/app/administration/src/example.spec.ts'));

            expect(isPlatformDeprecationRun()).toBe(true);
        });

        it('does not count the spec of a plugin as the platform’s run', () => {
            useSpecPath(extensionSpecPath);

            expect(isPlatformDeprecationRun()).toBe(false);
        });

        it('counts a call outside a test as the platform’s run', () => {
            useSpecPath(undefined);

            expect(isPlatformDeprecationRun()).toBe(true);
        });
    });

    it('fails on an unasserted deprecation in the platform’s run', () => {
        oldFunction();

        expect(failureOfEndDeprecationLog()).toContain(`1. ${oldFunctionMessage}`);
    });

    describe('in the Jest run of an extension', () => {
        beforeEach(() => {
            useSpecPath(extensionSpecPath);
        });

        it('records an unasserted deprecation without failing', () => {
            const warn = jest.spyOn(console, 'warn');

            oldFunction();
            triggerOtherDeprecation();

            expect(deprecations.entries.map(({ message }) => message)).toEqual([
                oldFunctionMessage,
                'otherFunction() is deprecated.',
            ]);
            expect(warn).not.toHaveBeenCalled();
            expect(failureOfEndDeprecationLog()).toBe('');
        });

        it('asserts a deprecation with toHaveTriggered', () => {
            oldFunction();

            expect(deprecations).toHaveTriggered(oldFunctionMessage);
        });

        it('lets allowDeprecation pass a deprecation', () => {
            allowDeprecation('oldFunction()');

            oldFunction();

            expect(deprecations.entries).toHaveLength(1);
            expect(failsTheTest(deprecations.entries[0], deprecations.allowed)).toBe(false);
        });

        it.activeFeatureFlags(['v7.0.0.0'])('still throws for a major the test activates', () => {
            expect(() => oldFunction()).toThrow(`Tried to access deprecated functionality: ${oldFunctionMessage}`);
        });

        it('reaches the production guard after useProductionDeprecationGuard', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
            useProductionDeprecationGuard();

            triggerOtherDeprecation();

            expect(warn).toHaveBeenCalledWith('[Deprecation]', 'otherFunction() is deprecated.');
        });
    });
});
