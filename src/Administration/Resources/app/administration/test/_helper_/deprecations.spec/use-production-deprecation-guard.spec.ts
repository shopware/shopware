/**
 * @sw-package framework
 */

import { deprecations, useProductionDeprecationGuard } from '../deprecations';
import oldFunction from '../_fixtures/deprecations/oldFunction';
import { oldFunctionMessage, startNextTest, triggerOtherDeprecation } from './deprecations-test-setup';

describe('test/_helper_/deprecations - useProductionDeprecationGuard', () => {
    it('sends the deprecation to console.warn instead of recording it', () => {
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
        useProductionDeprecationGuard();

        Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'productionGuard() is deprecated.');

        expect(warn).toHaveBeenCalledWith('[Deprecation]', 'productionGuard() is deprecated.');
        expect(deprecations).not.toHaveTriggered('productionGuard()');
    });

    it('records again once the test ends', () => {
        useProductionDeprecationGuard();
        startNextTest();

        oldFunction();

        expect(deprecations).toHaveTriggered(oldFunctionMessage);
    });

    describe('across two tests', () => {
        it('runs the production guard in the first test', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
            useProductionDeprecationGuard();

            triggerOtherDeprecation();

            expect(warn).toHaveBeenCalledWith('[Deprecation]', 'otherFunction() is deprecated.');
        });

        it('records again in the second test', () => {
            oldFunction();

            expect(deprecations).toHaveTriggered(oldFunctionMessage);
        });
    });
});
