/**
 * @sw-package framework
 */

import Feature from 'src/core/feature';
import FeatureService from 'src/app/service/feature.service';
import normalizeFeatureFlag from '../../_helper_/normalizeFeatureFlag';

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

export default new FeatureService(FeatureMock);
