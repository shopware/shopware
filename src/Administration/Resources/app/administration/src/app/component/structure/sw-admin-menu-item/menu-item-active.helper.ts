/**
 * @sw-package framework
 *
 * Which admin menu entry is active, derived from Vue Router's resolved `$route.matched` chain.
 */

type RouteLike = {
    name?: string;
    matched?: Array<{ name?: string }>;
    params?: Record<string, unknown>;
    meta?: {
        parentPath?: string;
    };
};

type RouterLike = {
    getRoutes?: () => Array<RouteLike>;
};

type MenuEntryLike = {
    id?: string;
    path?: string;
    params?: Record<string, unknown>;
    children?: MenuEntryLike[];
};

/**
 * Route names counting as "current": the `matched` chain plus everything reachable via `parentPath`.
 *
 * @private
 */
export function getActiveRouteNames(route?: RouteLike, router?: RouterLike): Set<string> {
    const names = new Set<string>();

    (route?.matched ?? []).forEach((record) => {
        if (record.name) {
            names.add(record.name);
        }
    });

    if (route?.name) {
        names.add(route.name);
    }

    const findRoute = (name: string) => router?.getRoutes?.().find((candidate) => candidate.name === name) ?? null;
    const visited = new Set<string>();
    let parentPath = route?.meta?.parentPath;

    while (parentPath && !visited.has(parentPath)) {
        visited.add(parentPath);
        names.add(parentPath);
        parentPath = findRoute(parentPath)?.meta?.parentPath ?? undefined;
    }

    return names;
}

/**
 * App, SDK and custom entity entries share a route name and differ only by params, so compare the
 * params the entry declares. Entries without params always match.
 *
 * @private
 */
export function entryParamsMatchRoute(entry?: MenuEntryLike, route?: RouteLike): boolean {
    if (!entry?.params) {
        return true;
    }

    const entryParams = entry.params;

    return Object.keys(entryParams).every((key) => String(route?.params?.[key]) === String(entryParams[key]));
}

/**
 * Whether the entry's own route is active, or for path-less grouping entries the descendant's.
 *
 * @private
 */
export function isEntryOnActiveRoute(
    entry?: MenuEntryLike,
    route?: RouteLike,
    activeNames: Set<string> = getActiveRouteNames(route),
): boolean {
    if (entry?.path && activeNames.has(entry.path) && entryParamsMatchRoute(entry, route)) {
        return true;
    }

    return (entry?.children ?? []).some((child) => isEntryOnActiveRoute(child, route, activeNames));
}
