/**
 * @sw-package framework
 * @private
 */

/**
 * Whether this is Shopware's own Jest run, whose `_jest.config.ts` sets the `__shopwarePlatformDeprecationRun`
 * global. An extension that reuses this test setup with its own Jest config is no platform run: there, the
 * deprecation recorder neither records, warns nor throws, because the deprecations come from core's own usages.
 * Remove it once core's deprecation baseline is stable, see
 * `adr/2026-10-23-fail-on-administration-deprecations-only-in-core-tests.md`.
 *
 * @private
 * @example
 * if (!isPlatformDeprecationRun()) {
 *     return;
 * }
 */
export function isPlatformDeprecationRun(): boolean {
    return (globalThis as { __shopwarePlatformDeprecationRun?: unknown }).__shopwarePlatformDeprecationRun === true;
}

/**
 * Unsets the marker `_jest.config.ts` sets for Shopware's own Jest run, so the deprecation recorder behaves as in
 * the Jest run of an extension, see `isPlatformDeprecationRun()`. Jest restores it after the test.
 *
 * @private
 * @example
 * beforeEach(() => {
 *     unsetPlatformDeprecationRunMarker();
 * });
 */
export function unsetPlatformDeprecationRunMarker(): void {
    jest.replaceProperty(
        globalThis as unknown as { __shopwarePlatformDeprecationRun: boolean },
        '__shopwarePlatformDeprecationRun',
        false,
    );
}
