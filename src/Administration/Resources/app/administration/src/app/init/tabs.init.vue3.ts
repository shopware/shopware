/**
 * @package admin
 */

// eslint-disable-next-line import/no-named-default
import type { TabItemEntry } from 'src/app/state/tabs.store';
import type { Router, RouteLocationNormalized, RouteRecordRaw } from 'vue-router_v3';

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default function initializeTabs(): void {
    Shopware.ExtensionAPI.handle('uiTabsAddTabItem', (componentConfig) => {
        Shopware.State.commit('tabs/addTabItem', componentConfig);

        // if current route does not exist check if they exists after adding the route
        // @ts-expect-error
        const router = Shopware.Application.view?.router as Router;

        const currentRoute = router.currentRoute.value;

        /* istanbul ignore next */
        if (
            router &&
            currentRoute.fullPath.includes(componentConfig.componentSectionId) &&
            currentRoute.matched.length <= 0
        ) {
            createRouteForTabItem(router.currentRoute.value, router, () => undefined);
            void router.replace(router.currentRoute.value.fullPath);
        }
    });

    /* istanbul ignore next */
    void Shopware.Application.viewInitialized.then(() => {
        // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
        // @ts-expect-error
        const router = Shopware.Application.view?.router as Router;

        const currentRoute = router.currentRoute.value;

        if (router && currentRoute.matched.length <= 0) {
            createRouteForTabItem(router.currentRoute.value, router, () => undefined);
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
                next(router.resolve(to.fullPath).fullPath);
            } else {
                next(to.fullPath);
            }
        });
    });
}

/* istanbul ignore next */
function createRouteForTabItem(to: RouteLocationNormalized, router: Router, next: () => void): boolean {
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
    const parentRoute = getParentRoute(dynamicPath, router);

    if (parentRoute && parentRoute?.children === undefined) {
        parentRoute.children = [];
    }

    if (parentRoute && parentRoute.children) {
        const firstChild = parentRoute.children[0];
        const parentRouteName: string = typeof parentRoute.name === 'string' ? parentRoute.name : '';
        const newRouteName = `${parentRouteName}.${matchingTabItemConfig.componentSectionId}`;

        let routeAlreadyExists = false;

        try {
            routeAlreadyExists = router.resolve({
                name: newRouteName,
            }).matched.some((route) => route.name === newRouteName);
        } catch (e) {
            // Do nothing when route does not exist
        }

        if (!routeAlreadyExists) {
            const newRoute: RouteRecordRaw = {
                path: dynamicPath,
                // @ts-expect-error
                component: Shopware.Application.view.getComponent('sw-extension-component-section'),
                name: newRouteName,
                meta: {
                    // eslint-disable-next-line max-len
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
                    parentPath: firstChild?.meta?.parentPath?.toString() ?? '',
                },
                isChildren: true,
                props: {
                    'position-identifier': matchingTabItemConfig.componentSectionId,
                },
            };

            router.addRoute(parentRoute.name?.toString() ?? '', newRoute);
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
    const resolvedParentRoute = router.resolve(childPath.split('/').slice(0, -1).join('/'));

    return Object.entries(resolvedParentRoute.params).reduce<string>((acc, [paramKey, paramValue]) => {
        const paramValueString = typeof paramValue === 'string' ? paramValue : paramValue.toString();

        return acc.replace(paramValueString, `:${paramKey}?`);
    }, childPath);
}

/* istanbul ignore next */
// eslint-disable-next-line max-len
/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-non-null-assertion */
function getParentRoute(
    dynamicPath: string,
    router: Router,
): RouteRecordRaw|undefined {
    const { routes } = router.options;

    const match = router.resolve(dynamicPath);

    // Get the matching object in the routes definition
    const routeForMatch = findMatchingRoute(routes, (route) => {
        return match.name === route.name;
    });

    // Get the parent route path
    const routeForMatchResolved = router.resolve(routeForMatch?.path ?? '');
    const parent = routeForMatchResolved.matched[routeForMatchResolved.matched.length - 1];
    const matchingParentRoute = parent ?? match;

    // Get the matching object for the parent route in the routes definition
    return findMatchingRoute(routes, (route) => {
        return matchingParentRoute?.path === route.path;
    });
}

/* istanbul ignore next */
function findMatchingRoute(
    routes: readonly RouteRecordRaw[],
    conditionCheck: (route: RouteRecordRaw) => boolean,
): RouteRecordRaw|undefined {
    const flattenRoutesDeep = (_routes: readonly RouteRecordRaw[]): readonly RouteRecordRaw[] => {
        return _routes.flatMap((route) => {
            const children = route.children ?? [];
            return [route, ...flattenRoutesDeep(children)];
        });
    };

    return flattenRoutesDeep(routes).find(conditionCheck);
}
