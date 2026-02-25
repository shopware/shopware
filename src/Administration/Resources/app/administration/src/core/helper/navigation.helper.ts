/**
 * @private
 * @sw-package framework
 *
 * Thin wrappers around window.location methods/properties.
 * JSDOM v26 makes window.location non-configurable so direct mocking in tests is impossible.
 * Tests should mock these helper functions instead.
 */

/**
 * @private
 */
export function reloadPage(): void {
    window.location.reload();
}

/**
 * @private
 */
export function navigateTo(url: string): void {
    window.location.href = url;
}

/**
 * @private
 */
export function getLocationHref(): string {
    return window.location.href;
}

/**
 * @private
 */
export function getLocationHostname(): string {
    return window.location.hostname;
}

/**
 * @private
 */
export function getLocationOrigin(): string {
    return window.location.origin;
}
