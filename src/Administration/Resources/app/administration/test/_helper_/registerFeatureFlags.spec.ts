/**
 * @sw-package framework
 */

import Feature from 'src/core/feature';
import { deprecations } from './deprecations';
import registerFeatureFlags from './registerFeatureFlags';

const featureConfig = {
    shopware: {
        feature: {
            flags: [
                { name: 'v6.8.0.0' },
                { name: 'ACCESSIBILITY_TWEAKS', default: true },
            ],
        },
    },
};

describe('test/_helper_/registerFeatureFlags', () => {
    beforeEach(() => {
        jest.replaceProperty(Feature, 'flags', {});
    });

    it('registers every flag in both spellings, active only when listed', () => {
        registerFeatureFlags(['v6.8.0.0'], featureConfig);

        expect(Feature.getAll()).toEqual({
            'V6.8.0.0': true,
            V6_8_0_0: true,
            ACCESSIBILITY_TWEAKS: false,
        });
    });

    it('lets the recorder take a guard of a registered major instead of reporting a typo', () => {
        expect(Feature.isKnownMajor('V6_8_0_0')).toBe(false);

        registerFeatureFlags([], featureConfig);
        Feature.triggerDeprecationOrThrow('V6_8_0_0', 'oldFunction() is deprecated.');

        expect(deprecations).toHaveTriggered('oldFunction() is deprecated.');
    });
});
