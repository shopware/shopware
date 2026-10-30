/**
 * @sw-package framework
 */

// A symbol so it cannot collide with anything a spec puts on `globalThis`.
const defaultActiveFeatureFlagsSymbol = Symbol.for('shopware.defaultActiveFeatureFlags');

/**
 * Returns the feature flags every test of this Jest run starts with; assert against these instead of
 * hard-coding them. `target` is the global to read from.
 *
 * @private
 */
export function getDefaultActiveFeatureFlags(target: object = globalThis): string[] {
    return (Reflect.get(target, defaultActiveFeatureFlagsSymbol) as string[] | undefined) ?? [];
}

/**
 * Replaces the default active feature flags, e.g. to simulate another major. Also set
 * `globalThis.activeFeatureFlags` when the flags are read at test registration time.
 *
 * @private
 */
export function setDefaultActiveFeatureFlags(flags: string[]): void {
    Reflect.set(globalThis, defaultActiveFeatureFlagsSymbol, flags);
}
