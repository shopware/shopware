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
function triggerDeprecationOrThrow(majorFlag, message, options) {
    // `FeatureMock` knows the flags of `it.activeFeatureFlags()`, a directly imported `Feature` only those a spec
    // sets with `Feature.init()`: the major counts as active when either does, and then nothing is silenced.
    const feature = FeatureMock.isActive(majorFlag) ? FeatureMock : this;

    if (shouldHandleDeprecationsThroughMock(majorFlag, feature.isActive(majorFlag), this.isKnownMajor(majorFlag))) {
        if (!feature.isSilenced(majorFlag)) {
            recordDeprecation(message, options?._blame);
        }

        return;
    }

    // The production guard checks the silencing itself, after its once-per-message check.
    productionTriggerDeprecationOrThrow.call(feature, majorFlag, message, options);
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
