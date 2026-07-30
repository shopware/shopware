/**
 * @sw-package framework
 *
 * Which admin menu entry is active, derived from Vue Router's resolved `$route.matched` chain.
 */

/**
 * Route names counting as "current": the `matched` chain plus everything reachable via `parentPath`.
 *
 * @private
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
 * App, SDK and custom entity entries share a route name and differ only by params, so compare the
 * params the entry declares. Entries without params always match.
 *
 * @private
 */
export function entryParamsMatchRoute(entry, route) {
    if (!entry?.params) {
        return true;
    }

    return Object.keys(entry.params).every((key) => String(route?.params?.[key]) === String(entry.params[key]));
}

/**
 * Whether the entry's own route is active, or for path-less grouping entries the descendant's.
 *
 * @private
 */
export function isEntryOnActiveRoute(entry, route, activeNames = getActiveRouteNames(route)) {
    if (entry?.path && activeNames.has(entry.path) && entryParamsMatchRoute(entry, route)) {
        return true;
    }

    return (entry?.children ?? []).some((child) => isEntryOnActiveRoute(child, route, activeNames));
}
