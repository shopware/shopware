/**
 * @sw-package framework
 */

import getMajorFeatureFlags from './majorFeatureFlags';

const config = {
    shopware: {
        feature: {
            flags: [
                { name: 'v6.7.0.0', major: true, default: true },
                { name: 'v6.8.0.0', major: true },
                { name: 'v6.9.0.0', major: true },
                { name: 'major-feature:next', major: true },
                { name: 'NEXT_MAJOR_FEATURE', major: 'v6.9.0.0' },
                { name: 'JSON_LD_DATA', major: 'v6.8.0.0' },
                { name: 'MINOR_FEATURE', major: false },
                { name: 'INVALID_CHILD', major: 'MINOR_FEATURE' },
            ],
        },
    },
};

describe('majorFeatureFlags', () => {
    it('enables every registered flag for any truthy FEATURE_ALL value', () => {
        expect(getMajorFeatureFlags(config, { FEATURE_ALL: 'minor' })).toEqual([
            'V6_7_0_0',
            'V6_8_0_0',
            'V6_9_0_0',
            'MAJOR_FEATURE_NEXT',
            'NEXT_MAJOR_FEATURE',
            'JSON_LD_DATA',
            'MINOR_FEATURE',
            'INVALID_CHILD',
        ]);
    });

    it('honors an explicit opt-out even when FEATURE_ALL is enabled', () => {
        expect(getMajorFeatureFlags(config, { FEATURE_ALL: '1', JSON_LD_DATA: '0' })).not.toContain('JSON_LD_DATA');
    });

    it('enables only the selected major and its sub-features', () => {
        expect(getMajorFeatureFlags(config, { V6_8_0_0: '1' })).toEqual([
            'V6_7_0_0',
            'V6_8_0_0',
            'JSON_LD_DATA',
        ]);
    });

    it('enables a later major and its sub-features without enabling an earlier inactive major', () => {
        expect(getMajorFeatureFlags(config, { V6_9_0_0: '1' })).toEqual([
            'V6_7_0_0',
            'V6_9_0_0',
            'NEXT_MAJOR_FEATURE',
        ]);
    });

    it('honors an explicit sub-feature override', () => {
        expect(getMajorFeatureFlags(config, { V6_8_0_0: '1', JSON_LD_DATA: 'false' })).toEqual([
            'V6_7_0_0',
            'V6_8_0_0',
        ]);
    });

    it('enables an explicitly selected feature outside a major run', () => {
        expect(getMajorFeatureFlags(config, { JSON_LD_DATA: '1' })).toEqual(['JSON_LD_DATA']);
    });

    it('does not inherit from a non-major flag', () => {
        expect(getMajorFeatureFlags(config, { V6_8_0_0: '1', MINOR_FEATURE: '1' })).not.toContain('INVALID_CHILD');
    });

    it.each([
        '',
        'false',
        '0',
    ])('activates nothing outside a major run: %s', (featureAll) => {
        expect(getMajorFeatureFlags(config, { FEATURE_ALL: featureAll })).toEqual([]);
    });
});
