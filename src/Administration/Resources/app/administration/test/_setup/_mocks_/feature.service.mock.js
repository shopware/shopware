/**
 * @package admin
 */

import Feature from 'src/app/service/feature.service';

/**
 * You can activate feature flags in the beforeAll method like this:
 * global.activeFeatureFlags = ['FEATURE_NEXT_12345'];
 */

global.activeFeatureFlags = [];

const featureMock = {
    isActive: (flagName) => {
        return global.activeFeatureFlags.includes(flagName);
    },
};

const feature = new Feature(featureMock);

export default feature;
