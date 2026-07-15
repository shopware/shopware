/**
 * @sw-package framework
 *
 * @private
 *
 * Route-derived active-state detection for the admin menu.
 *
 * Vue Router's resolved `$route.matched` chain (the current route plus all of its
 * ancestors) is the source of truth here. A navigation entry is therefore treated as
 * active when its own route is the current route or an ancestor of it, or when any of its
 * descendants is. This works for any routing shape without hand-tuned `parentPath`
 * declarations and, crucially, lights up path-less grouping parents (e.g. "Extensions")
 * whose active state can only be inherited from a child.
 */

/**
 * The set of route names in the currently resolved route chain (current route + ancestors).
 *
 * @param {Object} route - a Vue Router `$route`
 * @returns {Set<string>}
 */
export function getMatchedRouteNames(route) {
    return new Set((route?.matched ?? []).map((record) => record.name).filter(Boolean));
}

/**
 * Entries coming from apps, SDK modules, sales channels or custom entities can share a
 * single route name and differ only by their route params (e.g. many SDK items live on
 * `sw.extension.sdk.index`, distinguished by `params.id`). Only the params the entry itself
 * declares are compared, so entries without params always match.
 *
 * @param {Object} entry - a navigation entry
 * @param {Object} route - a Vue Router `$route`
 * @returns {boolean}
 */
export function entryParamsMatchRoute(entry, route) {
    if (!entry?.params) {
        return true;
    }

    return Object.keys(entry.params).every((key) => String(route?.params?.[key]) === String(entry.params[key]));
}

/**
 * Whether the entry's own route is the current route (or an ancestor of it) and its params
 * match, or — for path-less/grouping entries — whether any descendant entry is on the
 * active route.
 *
 * @param {Object} entry - a navigation entry (with an optional `children` array)
 * @param {Object} route - a Vue Router `$route`
 * @param {Set<string>} [matchedNames] - pre-computed matched route names for the route
 * @returns {boolean}
 */
export function isEntryOnActiveRoute(entry, route, matchedNames = getMatchedRouteNames(route)) {
    if (entry?.path && matchedNames.has(entry.path) && entryParamsMatchRoute(entry, route)) {
        return true;
    }

    return (entry?.children ?? []).some((child) => isEntryOnActiveRoute(child, route, matchedNames));
}
