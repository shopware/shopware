/**
 * @sw-package framework
 */

import getMajorFeatureFlags from './majorFeatureFlags';

const config = {
    shopware: {
        feature: {
            flags: [
                { name: 'v6.8.0.0', major: true },
                { name: 'v6.9.0.0', major: true },
                { name: 'major-feature:next', major: true },
                { name: 'NEXT_MAJOR_FEATURE', major: true, majorVersion: 'v6.9.0.0' },
                { name: 'MINOR_FEATURE', major: false },
            ],
        },
    },
};

describe('majorFeatureFlags', () => {
    it('returns normalized flags marked as major', () => {
        expect(getMajorFeatureFlags(config)).toEqual([
            'V6_8_0_0',
            'V6_9_0_0',
            'MAJOR_FEATURE_NEXT',
            'NEXT_MAJOR_FEATURE',
        ]);
    });

    it('leaves out the majors arriving after the targeted one', () => {
        expect(getMajorFeatureFlags(config, 'v6.8.0.0')).toEqual([
            'V6_8_0_0',
            'MAJOR_FEATURE_NEXT',
        ]);
    });

    it('returns every major up to and including the targeted one', () => {
        expect(getMajorFeatureFlags(config, 'v6.9.0.0')).toEqual([
            'V6_8_0_0',
            'V6_9_0_0',
            'MAJOR_FEATURE_NEXT',
            'NEXT_MAJOR_FEATURE',
        ]);
    });

    it.each([
        '',
        'false',
        '1',
        'minor',
    ])('activates nothing outside a major run: %s', (featureAll) => {
        expect(getMajorFeatureFlags(config, featureAll)).toEqual([]);
    });
});
