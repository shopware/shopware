import Feature from 'src/app/service/feature.service';

global.activeFeatureFlags = [];

const featureMock = {
    isActive: (flagName) => {
        return global.activeFeatureFlags.includes(flagName);
    }
};

const feature = new Feature(featureMock);

export default feature;
