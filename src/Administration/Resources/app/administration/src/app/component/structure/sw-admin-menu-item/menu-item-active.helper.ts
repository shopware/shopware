/**
 * @sw-package framework
 *
 * Which admin menu entry is active, derived from Vue Router's resolved `$route.matched` chain.
 */

import type { ModuleManifest, ModuleTypes } from 'src/core/factory/module.factory';

type ModuleNavigationPath = Pick<Exclude<ModuleManifest['navigation'], undefined>[number], 'path'>;

type RouteLike = {
    name?: string;
    matched?: Array<{ name?: string }>;
    params?: Record<string, unknown>;
    meta?: {
        parentPath?: string;
        // Written for every module route by `addModuleInfoToTarget`, see core/factory/router.factory
        $module?: { type?: ModuleTypes; navigation?: ModuleNavigationPath[] };
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
 * Stand-in for a missing `parentPath`: the menu entries the route's own module contributes.
 *
 * Extensions cannot be asked to declare `parentPath` retroactively, so an ambiguous set is used as-is
 * and highlights the module's entries. Core modules declare it, so there the ambiguity is declined.
 */
function ownModuleMenuPaths(route: RouteLike | undefined, activeNames: Set<string>): string[] {
    const module = route?.meta?.$module;
    const menuPaths = (module?.navigation ?? []).map((entry) => entry.path).filter((path): path is string => !!path);

    if (menuPaths.some((path) => activeNames.has(path))) {
        return [];
    }

    if (menuPaths.length > 1 && module?.type === 'core') {
        return [];
    }

    return menuPaths;
}

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
    // An explicit `parentPath` wins; the module's own entries fill in for routes that declare none.
    const pending = route?.meta?.parentPath ? [route.meta.parentPath] : ownModuleMenuPaths(route, names);

    while (pending.length) {
        const parentPath = pending.shift() as string;

        if (visited.has(parentPath)) {
            continue;
        }

        visited.add(parentPath);
        names.add(parentPath);

        const grandParentPath = findRoute(parentPath)?.meta?.parentPath;

        if (grandParentPath) {
            pending.push(grandParentPath);
        }
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
