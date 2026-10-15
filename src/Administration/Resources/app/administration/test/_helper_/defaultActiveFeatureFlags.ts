/**
 * @sw-package framework
 */

// A symbol so it cannot collide with anything a spec puts on `globalThis`.
// `test/_setup/feature-flag-test-environment.js` reads the same key on its `this.global`.
const defaultActiveFeatureFlagsSymbol = Symbol.for('shopware.defaultActiveFeatureFlags');

/**
 * Returns the default active feature flags: the flags every test of this Jest run starts with.
 * `it.activeFeatureFlags()` adds its per-test flags on top of them, and each test's `beforeEach`
 * resets `globalThis.activeFeatureFlags` to them.
 *
 * Use it to assert relative to the run's baseline instead of hard-coding it, so a spec passes in
 * every lane (`V6_8_0_0=1`, `FEATURE_ALL`, ...).
 *
 * ```
 * expect(globalThis.activeFeatureFlags).toEqual([...getDefaultActiveFeatureFlags(), 'NEW_FEATURE']);
 * ```
 *
 * @private
 */
export function getDefaultActiveFeatureFlags(): string[] {
    return (Reflect.get(globalThis, defaultActiveFeatureFlagsSymbol) as string[] | undefined) ?? [];
}

/**
 * Replaces the default active feature flags (see `getDefaultActiveFeatureFlags()`). Stores the
 * array as given, not a copy.
 *
 * Use it in a spec that simulates the run of another major, so its assertions cover that lane
 * without a second Jest config. Set `globalThis.activeFeatureFlags` as well when flags are read at
 * registration time, as `it.deprecated()` does, because no `beforeEach` has reset them yet.
 *
 * ```
 * setDefaultActiveFeatureFlags(['V6_8_0_0']);
 * globalThis.activeFeatureFlags = ['V6_8_0_0'];
 * ```
 *
 * @private
 */
export function setDefaultActiveFeatureFlags(flags: string[]): void {
    Reflect.set(globalThis, defaultActiveFeatureFlagsSymbol, flags);
}
