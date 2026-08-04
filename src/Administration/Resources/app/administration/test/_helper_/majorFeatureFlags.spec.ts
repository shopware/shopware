/**
 * @sw-package framework
 */

import getMajorFeatureFlags from './majorFeatureFlags';

describe('majorFeatureFlags', () => {
    it('returns normalized flags marked as major', () => {
        expect(
            getMajorFeatureFlags({
                shopware: {
                    feature: {
                        flags: [
                            { name: 'v6.8.0.0', major: true },
                            { name: 'major-feature:next', major: true },
                            { name: 'MINOR_FEATURE', major: false },
                        ],
                    },
                },
            }),
        ).toEqual([
            'V6_8_0_0',
            'MAJOR_FEATURE_NEXT',
        ]);
    });
});
