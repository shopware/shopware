/**
 * Thin abstraction over window.location for testability.
 * JSDOM v26 makes window.location non-configurable, so direct mocking
 * is impossible. Tests mock these functions instead.
 *
 * @sw-package framework
 */

export function getLocationHref() {
    return window.location.href;
}

export function getLocationSearch() {
    return window.location.search;
}

export function navigateTo(url) {
    window.location.href = url;
}

export function reloadPage() {
    window.location.reload();
}

export function assignLocation(url) {
    window.location.assign(url);
}
