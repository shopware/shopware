/**
 * @sw-package framework
 */
import {
    getMajorFeatureFlags,
    isMajorRun,
    isSameFlag,
    loadFeatureConfig,
    majorFlagsOf,
    normalizeFeatureFlag,
    versionOfFlag,
} from './feature-flags';

describe('scripts/feature-flags', () => {
    it('reads the flags of feature.yaml', () => {
        expect(loadFeatureConfig().shopware?.feature?.flags).toEqual(
            expect.arrayContaining([expect.objectContaining({ name: expect.stringMatching(/^v6\.\d+\.0\.0$/) })]),
        );
    });

    it.each([
        ['v6.8.0.0', 'V6_8_0_0'],
        ['V6_8_0_0', 'V6_8_0_0'],
        ['feature-next:12345', 'FEATURE_NEXT_12345'],
    ])('normalizes %s to %s', (flag, normalized) => {
        expect(normalizeFeatureFlag(flag)).toBe(normalized);
    });

    it('treats both spellings of a flag as the same flag', () => {
        expect(isSameFlag('v6.8.0.0', 'V6_8_0_0')).toBe(true);
        expect(isSameFlag('v6.8.0.0', 'v6.9.0.0')).toBe(false);
    });

    it.each([
        ['v6.8.0.0', [6, 8]],
        ['V6_10_0_0', [6, 10]],
        ['v6.8.1.0', null],
        ['EXAMPLE_REWORK', null],
    ])('reads the version of %s', (flag, version) => {
        expect(versionOfFlag(flag)).toEqual(version);
    });

    describe('majorFlagsOf', () => {
        const flags = [
            { name: 'v6.7.0.0', default: true },
            { name: 'v6.9.0.0', default: false },
            { name: 'v6.8.0.0', default: false },
            { name: 'EXAMPLE_REWORK', default: false, major: 'v6.8.0.0' },
            { name: 'LATER_REWORK', default: false, major: 'v6.9.0.0' },
            { name: 'EXPERIMENT', default: false },
        ];

        it('takes the next major and the flags that belong to it', () => {
            expect(majorFlagsOf({ shopware: { feature: { flags } } })).toEqual([
                'v6.8.0.0',
                'EXAMPLE_REWORK',
            ]);
        });

        it('fails when every version flag is on already', () => {
            expect(() => majorFlagsOf({ shopware: { feature: { flags: [{ name: 'v6.7.0.0', default: true }] } } })).toThrow(
                'feature.yaml has no version flag that is still off',
            );
        });
    });

    const config = {
        shopware: {
            feature: {
                flags: [
                    { name: 'v6.7.0.0', default: true },
                    { name: 'v6.8.0.0' },
                    { name: 'v6.9.0.0' },
                    { name: 'ACCESSIBILITY_TWEAKS', default: true },
                    { name: 'NEXT_MAJOR_FEATURE', major: 'v6.9.0.0' },
                    { name: 'JSON_LD_DATA', major: 'v6.8.0.0' },
                    { name: 'MINOR_FEATURE' },
                    { name: 'INVALID_CHILD', major: 'MINOR_FEATURE' },
                ],
            },
        },
    };

    describe('getMajorFeatureFlags', () => {
        it('enables every registered flag for any truthy FEATURE_ALL value', () => {
            expect(getMajorFeatureFlags(config, { FEATURE_ALL: 'minor' })).toEqual([
                'V6_7_0_0',
                'V6_8_0_0',
                'V6_9_0_0',
                'ACCESSIBILITY_TWEAKS',
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
                'ACCESSIBILITY_TWEAKS',
                'JSON_LD_DATA',
            ]);
        });

        it('enables a later major and its sub-features without enabling an earlier inactive major', () => {
            expect(getMajorFeatureFlags(config, { V6_9_0_0: '1' })).toEqual([
                'V6_7_0_0',
                'V6_9_0_0',
                'ACCESSIBILITY_TWEAKS',
                'NEXT_MAJOR_FEATURE',
            ]);
        });

        it('honors an explicit sub-feature override', () => {
            expect(getMajorFeatureFlags(config, { V6_8_0_0: '1', JSON_LD_DATA: 'false' })).toEqual([
                'V6_7_0_0',
                'V6_8_0_0',
                'ACCESSIBILITY_TWEAKS',
            ]);
        });

        it('honors an explicit opt-out for a default-true flag in the major lane', () => {
            expect(getMajorFeatureFlags(config, { V6_8_0_0: '1', ACCESSIBILITY_TWEAKS: '0' })).not.toContain(
                'ACCESSIBILITY_TWEAKS',
            );
        });

        it('enables an explicitly selected feature outside a major run', () => {
            expect(getMajorFeatureFlags(config, { JSON_LD_DATA: '1' })).toEqual(['JSON_LD_DATA']);
        });

        it('keeps an explicitly enabled independent feature in a major run', () => {
            expect(getMajorFeatureFlags(config, { V6_8_0_0: '1', MINOR_FEATURE: '1' })).toContain('MINOR_FEATURE');
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

        it.each([
            ['the major flag', { V6_8_0_0: '1' }, true],
            ['FEATURE_ALL', { FEATURE_ALL: '1' }, true],
            ['no flag', {}, false],
            ['a flag other than a major', { MINOR_FEATURE: '1' }, false],
        ])('tells a major run from the environment with %s', (_, environment: Record<string, string>, majorRun: boolean) => {
            expect(isMajorRun(config, environment)).toBe(majorRun);
        });
    });
});
