/**
 * @sw-package framework
 */

const defaultActiveFeatureFlagsSymbol = Symbol.for('shopware.defaultActiveFeatureFlags');
const majorFeatureFlags = ['V6_8_0_0'];

Reflect.set(globalThis, defaultActiveFeatureFlagsSymbol, majorFeatureFlags);
globalThis.activeFeatureFlags = [...majorFeatureFlags];

describe('Jest feature flag extensions with a major baseline', () => {
    let deprecatedTestRan = false;
    let featureFlagsInSetup: string[] = [];

    beforeEach(() => {
        featureFlagsInSetup = [...globalThis.activeFeatureFlags];
    });

    afterAll(() => {
        // eslint-disable-next-line jest/no-standalone-expect -- Verifies the environment restores the baseline after teardown.
        expect(globalThis.activeFeatureFlags).toEqual(majorFeatureFlags);

        // eslint-disable-next-line jest/no-standalone-expect -- Verifies the normalized major flag skipped the test.
        expect(deprecatedTestRan).toBeFalsy();
    });

    it.deprecated('v6.8.0.0')('skips a deprecated test for the normalized major flag', () => {
        deprecatedTestRan = true;
    });

    it.activeFeatureFlags(['EXPERIMENTAL_FEATURE'])('adds per-test flags without replacing the major baseline', () => {
        // eslint-disable-next-line jest/no-standalone-expect -- The custom test helper is not known to eslint-plugin-jest.
        expect(globalThis.activeFeatureFlags).toEqual([
            'V6_8_0_0',
            'EXPERIMENTAL_FEATURE',
        ]);
        // eslint-disable-next-line jest/no-standalone-expect -- The custom test helper is not known to eslint-plugin-jest.
        expect(featureFlagsInSetup).toEqual([
            'V6_8_0_0',
            'EXPERIMENTAL_FEATURE',
        ]);
        // eslint-disable-next-line jest/no-standalone-expect -- The custom test helper is not known to eslint-plugin-jest.
        expect(Shopware.Feature.isActive('v6.8.0.0')).toBeTruthy();
    });
});
