/**
 * @sw-package framework
 */

// A symbol so it cannot collide with anything a spec puts on `globalThis`.
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
 * `target` is the global to read from; the Jest environment passes the test realm's global.
 *
 * @private
 */
export function getDefaultActiveFeatureFlags(target: object = globalThis): string[] {
    return (Reflect.get(target, defaultActiveFeatureFlagsSymbol) as string[] | undefined) ?? [];
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
