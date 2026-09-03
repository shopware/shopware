/**
 * @sw-package framework
 */

const defaultActiveFeatureFlagsSymbol = Symbol.for('shopware.defaultActiveFeatureFlags');
const majorFeatureFlags = ['V6_8_0_0'];

/**
 * Pretends the runner was started with the v6.8 flag on, so the assertions below cover the major
 * suite without needing a second Jest config.
 *
 * `Reflect.set` rather than plain assignment only because the key is a `Symbol`: `globalThis[sym] = x`
 * is not expressible in TypeScript without widening `globalThis`, whereas `Reflect.set` takes any
 * property key and type-checks as-is. Runtime behaviour is identical to an assignment. The baseline is
 * kept under a symbol so it cannot collide with anything a spec puts on `globalThis`; the companion
 * read in `jest-extensions.spec.ts` uses `Reflect.get` for the same reason.
 */
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

    // @deprecated tag:v6.8.0 - The test will be removed with the v6.8 major-baseline fixture.
    it.deprecated('v6.8.0.0')('skips a deprecated test for the normalized major flag', () => {
        deprecatedTestRan = true;
    });

    it.activeFeatureFlags(['EXPERIMENTAL_FEATURE'])('adds per-test flags without replacing the major baseline', () => {
        expect(globalThis.activeFeatureFlags).toEqual([
            'V6_8_0_0',
            'EXPERIMENTAL_FEATURE',
        ]);
        expect(featureFlagsInSetup).toEqual([
            'V6_8_0_0',
            'EXPERIMENTAL_FEATURE',
        ]);
        expect(Shopware.Feature.isActive('v6.8.0.0')).toBeTruthy();
    });

});
