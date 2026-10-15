/**
 * @sw-package framework
 */

import Feature from 'src/core/feature';
import FeatureService from 'src/app/service/feature.service';
import normalizeFeatureFlag from '../../_helper_/normalizeFeatureFlag';
import { recordDeprecation, shouldHandleDeprecationsThroughMock } from '../../_helper_/deprecations';

global.activeFeatureFlags = global.activeFeatureFlags ?? [];

/**
 * The real `Feature`, with the flags the test activates through `it.activeFeatureFlags()`. Stub any
 * method with `jest.spyOn(Shopware.Feature, 'isActive')`, and the rest of `Feature` uses the stub.
 */
export class FeatureMock extends Feature {
    static isActive(flagName) {
        const normalizedFlagName = normalizeFeatureFlag(flagName);

        return global.activeFeatureFlags.some((featureFlag) => normalizeFeatureFlag(featureFlag) === normalizedFlagName);
    }
}

const productionTriggerDeprecationOrThrow = Feature.triggerDeprecationOrThrow;

/**
 * Mocks the real `triggerDeprecationOrThrow` and silences deprecations if they are explicitly asserted or allowed
 */
function triggerDeprecationOrThrow(majorFlag, message) {
    if (shouldHandleDeprecationsThroughMock(majorFlag, this.isActive(majorFlag))) {
        recordDeprecation(message);
        return;
    }

    // With the caller's `this`, so `FeatureMock` keeps the flags a test activates.
    productionTriggerDeprecationOrThrow.call(this, majorFlag, message);
}

Feature.triggerDeprecationOrThrow = triggerDeprecationOrThrow;

// `jest.resetModules()` keeps module mocks, so a copy of `src/core/feature` loaded after it gets the override and
// the run's flags too.
jest.doMock('src/core/feature', () => {
    const featureModule = jest.requireActual('src/core/feature');
    featureModule.default.init(Feature.getAll());
    featureModule.default.triggerDeprecationOrThrow = triggerDeprecationOrThrow;

    return featureModule;
});

export default new FeatureService(FeatureMock);
