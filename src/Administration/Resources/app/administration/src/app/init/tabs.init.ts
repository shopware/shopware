/**
 * @package admin
 */

// eslint-disable-next-line import/no-named-default
import type { Route, RouteConfig, RawLocation, default as Router } from 'vue-router';
import type { TabItemEntry } from 'src/app/state/tabs.store';

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default function initializeTabs(): void {
    Shopware.ExtensionAPI.handle('uiTabsAddTabItem', (componentConfig) => {
        Shopware.State.commit('tabs/addTabItem', componentConfig);

        // if current route does not exist check if they exists after adding the route
        const router = Shopware.Application.view?.router;

        /* istanbul ignore next */
        if (
            router &&
            router.currentRoute.fullPath.includes(componentConfig.componentSectionId) &&
            router.currentRoute.matched.length <= 0
        ) {
            createRouteForTabItem(router.currentRoute, router, () => undefined);
        }
    });

    /* istanbul ignore next */
    void Shopware.Application.viewInitialized.then(() => {
        // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
        const router = Shopware.Application.view!.router!;

        if (router && router.currentRoute.matched.length <= 0) {
            createRouteForTabItem(router.currentRoute, router, () => undefined);
        }

        /* istanbul ignore next */
        router.beforeEach((to, from, next) => {
            if (to.matched.length > 0) {
                next();
                return;
            }

            const routeSuccess = createRouteForTabItem(to, router, next);

            // only resolve route if it was created
            if (routeSuccess) {
                next(router.resolve(to.fullPath).route as RawLocation);
            } else {
                next();
            }
        });
    });
}

/* istanbul ignore next */
function createRouteForTabItem(to: Route, router: Router, next: () => void): boolean {
    /**
     * Create new route for the url if it matches a tab extension
     */
    let matchingTabItemConfig: undefined | TabItemEntry;

    Object.values(Shopware.State.get('tabs').tabItems).find((tabItemConfigs) => {
        const _matchingTabItemConfig = tabItemConfigs.find((tabItemConfig) => {
            return to.fullPath.endsWith(tabItemConfig.componentSectionId);
        });

        if (_matchingTabItemConfig) {
            matchingTabItemConfig = _matchingTabItemConfig;
            return true;
        }

        return false;
    });

    if (!matchingTabItemConfig) {
        next();
        return false;
    }

    const dynamicPath = getDynamicPath(to.fullPath, router);
    const parentRoute = getParentRoute(dynamicPath, router as Router & { options?: { routes: RouteConfig[] } });

    if (parentRoute && parentRoute?.children === undefined) {
        parentRoute.children = [];
    }

    if (parentRoute && parentRoute.children) {
        const firstChild = parentRoute.children[0];
        const newRouteName = `${parentRoute.name ?? ''}.${matchingTabItemConfig.componentSectionId}`;

        const routeAlreadyExists = router.match({
            name: newRouteName,
        }).matched.some((route) => route.name === newRouteName);

        if (!routeAlreadyExists) {
            router.addRoute(parentRoute.name, {
                path: dynamicPath,
                // @ts-expect-error
                component: Shopware.Application.view.getComponent('sw-extension-component-section'),
                name: newRouteName,
                meta: {
                    // eslint-disable-next-line max-len
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
                    parentPath: firstChild?.meta?.parentPath ?? '',
                },
                isChildren: true,
                props: {
                    'position-identifier': matchingTabItemConfig.componentSectionId,
                },
            });
        }
    }

    return !!parentRoute?.children;
}

/* istanbul ignore next */
function getDynamicPath(childPath: string, router: Router): string {
    /**
     * Replace childPath static values with dynamic values
     *
     * Before: "sw/product/detail/f6167bd4a9c1438c88c3bcc4949809e9/my-awesome-app-example-product-view"
     * After: "/sw/product/detail/:id/my-awesome-app-example-product-view"
     */
    const resolvedParentRoute = router.resolve(childPath.split('/').slice(0, -1).join('/')).route;

    return Object.entries(resolvedParentRoute.params).reduce<string>((acc, [paramKey, paramValue]) => {
        return acc.replace(paramValue, `:${paramKey}?`);
    }, childPath);
}

/* istanbul ignore next */
// eslint-disable-next-line max-len
/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-non-null-assertion */
function getParentRoute(
    dynamicPath: string,
    router: Router & { options?: { routes: RouteConfig[] } },
): RouteConfig|undefined {
    const { routes } = router.options;

    const match = router.match(dynamicPath);

    // Get the matching object in the routes definition
    const routeForMatch = findMatchingRoute(routes, (route) => {
        return match.name === route.name;
    });

    // Get the parent route path
    const routeForMatchResolved = router.match(routeForMatch?.path ?? '');
    const parent = routeForMatchResolved.matched[routeForMatchResolved.matched.length - 1]?.parent;
    const matchingParentRoute = parent ?? match;

    // Get the matching object for the parent route in the routes definition
    return findMatchingRoute(routes, (route) => {
        return matchingParentRoute?.path === route.path;
    });
}

/* istanbul ignore next */
function findMatchingRoute(
    routes: RouteConfig[],
    conditionCheck: (route: RouteConfig) => boolean,
): RouteConfig|undefined {
    const flattenRoutesDeep = (_routes: RouteConfig[]): RouteConfig[] => {
        return _routes.flatMap((route) => {
            const children = route.children ?? [];
            return [route, ...flattenRoutesDeep(children)];
        });
    };

    return flattenRoutesDeep(routes).find(conditionCheck);
}
