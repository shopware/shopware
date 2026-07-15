/**
 * @sw-package framework
 *
 * @private
 *
 * Route-derived active-state detection for the admin menu.
 *
 * Vue Router's resolved `$route.matched` chain (the current route plus all of its ancestors)
 * is the source of truth here — the same data Vue Router itself uses to add `router-link-active`
 * to `<router-link>`s. A navigation entry is therefore treated as active when its own route is
 * the current route or an ancestor of it, or when any of its descendants is. This works for any
 * routing shape and lights up path-less grouping parents (e.g. "Extensions") whose active state
 * can only be inherited from a child.
 *
 * Detail/create pages are usually registered as *siblings* of their owning list route rather than
 * as nested children, so they do not appear in `matched`. They are bridged to their owning
 * navigation route by following the `meta.parentPath` chain across the registered routes — the
 * same convention the router guard uses to resolve `$route.meta.$current`, but resolved here so
 * the menu does not depend on that value being present (it is `null` for e.g. the Extensions and
 * Settings sub-trees).
 */

/**
 * The set of route names that count as "current" for the given route: the resolved `matched`
 * chain plus every navigation route reachable from it via the `meta.parentPath` chain.
 *
 * @private
 * @param {Object} route - a Vue Router `$route`
 * @param {Object} [router] - a Vue Router instance (used to walk the `parentPath` chain)
 * @returns {Set<string>}
 */
export function getActiveRouteNames(route, router) {
    const names = new Set();

    (route?.matched ?? []).forEach((record) => {
        if (record.name) {
            names.add(record.name);
        }
    });

    if (route?.name) {
        names.add(route.name);
    }

    const findRoute = (name) => router?.getRoutes?.().find((candidate) => candidate.name === name) ?? null;
    const visited = new Set();
    let parentPath = route?.meta?.parentPath;

    while (parentPath && !visited.has(parentPath)) {
        visited.add(parentPath);
        names.add(parentPath);
        parentPath = findRoute(parentPath)?.meta?.parentPath ?? null;
    }

    return names;
}

/**
 * Entries coming from apps, SDK modules, sales channels or custom entities can share a single
 * route name and differ only by their route params (e.g. many SDK items live on
 * `sw.extension.sdk.index`, distinguished by `params.id`). Only the params the entry itself
 * declares are compared, so entries without params always match.
 *
 * @private
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
 * Whether the entry's own route is the current route (or an ancestor of it) and its params match,
 * or — for path-less/grouping entries — whether any descendant entry is on the active route.
 *
 * @private
 * @param {Object} entry - a navigation entry (with an optional `children` array)
 * @param {Object} route - a Vue Router `$route`
 * @param {Set<string>} [activeNames] - pre-computed active route names for the route
 * @returns {boolean}
 */
export function isEntryOnActiveRoute(entry, route, activeNames = getActiveRouteNames(route)) {
    if (entry?.path && activeNames.has(entry.path) && entryParamsMatchRoute(entry, route)) {
        return true;
    }

    return (entry?.children ?? []).some((child) => isEntryOnActiveRoute(child, route, activeNames));
}
