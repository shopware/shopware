/**
 * @sw-package framework
 */

import Feature from 'src/core/feature';
import { getDefaultActiveFeatureFlags, setDefaultActiveFeatureFlags } from '../defaultActiveFeatureFlags';
import { deprecations, endDeprecationLog, startDeprecationLog } from '../deprecations';
import oldFunction from '../_fixtures/deprecations/oldFunction';
import { oldFunctionMessage, triggerOtherDeprecation } from './deprecations-test-setup';

describe('test/_helper_/deprecations - the guard in Jest', () => {
    it('records a deprecation instead of warning', () => {
        const warn = jest.spyOn(console, 'warn');

        triggerOtherDeprecation();

        expect(warn).not.toHaveBeenCalled();
        expect(deprecations).toHaveTriggered('otherFunction() is deprecated.');
    });

    it('records a component name without the suffix of wrapTestComponent', () => {
        Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'The component "sw-legacy__wrapped" is deprecated.');

        expect(deprecations).toHaveTriggered('The component "sw-legacy" is deprecated.');
        expect(deprecations).not.toHaveTriggered('__wrapped');
    });

    it('records a module that imports Feature directly', () => {
        oldFunction();

        expect(deprecations).toHaveTriggered(oldFunctionMessage);
    });

    it.activeFeatureFlags(['v7.0.0.0'])('still throws at the call for a major the test activates itself', () => {
        expect(() => Shopware.Feature.triggerDeprecationOrThrow('V7_0_0_0', oldFunctionMessage)).toThrow(
            `Tried to access deprecated functionality: ${oldFunctionMessage}`,
        );
        expect(deprecations).not.toHaveTriggered(oldFunctionMessage);
    });

    it('runs the production guard outside a test', () => {
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
        endDeprecationLog();

        Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'outsideTest() is deprecated.');
        startDeprecationLog();

        expect(warn).toHaveBeenCalledWith('[Deprecation]', 'outsideTest() is deprecated.');
        expect(deprecations).not.toHaveTriggered('outsideTest()');
    });

    describe('in a run that activates the major', () => {
        const runFlags = getDefaultActiveFeatureFlags();

        beforeEach(() => {
            setDefaultActiveFeatureFlags([...runFlags, 'V7_0_0_0']);
            Feature.init({ V7_0_0_0: true });
        });

        afterEach(() => {
            setDefaultActiveFeatureFlags(runFlags);
            Feature.init({ V7_0_0_0: false });
        });

        it('records a deprecation instead of throwing at the call', () => {
            expect(() => oldFunction()).not.toThrow();

            expect(deprecations).toHaveTriggered(oldFunctionMessage);
        });
    });

    describe('after jest.resetModules()', () => {
        beforeEach(() => {
            jest.resetModules();
        });

        it('records the deprecations of the Feature copy a spec loads then', async () => {
            const { default: reloadedOldFunction } = await import('../_fixtures/deprecations/oldFunction');

            reloadedOldFunction();

            expect(deprecations).toHaveTriggered(oldFunctionMessage);
        });

        it("gives the Feature copy the run's flags", async () => {
            const { default: reloadedFeature } = await import('src/core/feature');

            expect(reloadedFeature).not.toBe(Feature);
            expect(reloadedFeature.getAll()).toEqual(Feature.getAll());
        });
    });
});
