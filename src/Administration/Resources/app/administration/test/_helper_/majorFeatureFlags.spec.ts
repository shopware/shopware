/**
 * @sw-package framework
 */

import getMajorFeatureFlags, { isMajorRun, loadFeatureConfig } from './majorFeatureFlags';

describe('test/_helper_/majorFeatureFlags', () => {
    it('resolves the major flags of feature.yaml for extension Jest setups', () => {
        const config = loadFeatureConfig();

        expect(getMajorFeatureFlags(config, { V6_8_0_0: '1' })).toContain('V6_8_0_0');
        expect(isMajorRun(config, { V6_8_0_0: '1' })).toBe(true);
        expect(isMajorRun(config, {})).toBe(false);
    });
});
