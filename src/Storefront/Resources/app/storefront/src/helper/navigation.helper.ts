/**
 * Thin abstraction over window.location for testability.
 * JSDOM v26 makes window.location non-configurable, so direct mocking
 * is impossible. Tests mock these functions instead.
 *
 * @sw-package framework
 */

export function getLocationHref(): string {
    return window.location.href;
}

export function getLocationSearch(): string {
    return window.location.search;
}

export function navigateTo(url: string): void {
    window.location.href = url;
}

export function reloadPage(): void {
    window.location.reload();
}

export function assignLocation(url: string): void {
    window.location.assign(url);
}
