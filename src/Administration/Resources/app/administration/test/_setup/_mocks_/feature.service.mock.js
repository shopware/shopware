/**
 * @sw-package framework
 */

import FeatureService from 'src/app/service/feature.service';
import Feature from 'src/core/feature';
import normalizeFeatureFlag from '../../_helper_/normalizeFeatureFlag';

/**
 * You can activate feature flags in the beforeAll method like this:
 * global.activeFeatureFlags = ['FEATURE_NEXT_12345'];
 */

global.activeFeatureFlags = global.activeFeatureFlags ?? [];

const featureMock = {
    isActive: (flagName) => {
        const normalizedFlagName = normalizeFeatureFlag(flagName);

        return global.activeFeatureFlags.some((featureFlag) => {
            return normalizeFeatureFlag(featureFlag) === normalizedFlagName;
        });
    },
    triggerDeprecationOrThrow: Feature.triggerDeprecationOrThrow,
};

const feature = new FeatureService(featureMock);

export default feature;
