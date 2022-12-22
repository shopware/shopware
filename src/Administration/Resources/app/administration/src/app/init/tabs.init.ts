/**
 * @package admin
 */

// eslint-disable-next-line import/no-named-default
import type { Route, RouteConfig, default as Router } from 'vue-router';
import type { TabItemEntry } from 'src/app/state/tabs.store';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeTabs(): void {
    Shopware.ExtensionAPI.handle('uiTabsAddTabItem', (componentConfig) => {
        Shopware.State.commit('tabs/addTabItem', componentConfig);

        // if current route does not exist check if they exists after adding the route
        const router = Shopware.Application.view?.router;

        /* istanbul ignore next */
        if (router && router.currentRoute.matched.length <= 0) {
            createRouteForTabItem(router.currentRoute, router, () => undefined);

            router.replace(router.resolve(router.currentRoute.fullPath).route);
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

            createRouteForTabItem(to, router, next);

            next(router.resolve(to.fullPath).route);
        });
    });
}

/* istanbul ignore next */
function createRouteForTabItem(to: Route, router: Router, next: () => void): void {
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
        return;
    }

    const dynamicPath = getDynamicPath(to.fullPath, router);
    const parentRoute = getParentRoute(dynamicPath, router);

    if (parentRoute && parentRoute.children) {
        const firstChild = parentRoute.children[0];

        parentRoute.children.push({
            path: dynamicPath,
            // @ts-expect-error
            component: Shopware.Application.view.getComponent('sw-extension-component-section'),
            name: `${parentRoute.name ?? ''}.${matchingTabItemConfig.componentSectionId}`,
            meta: {
                // eslint-disable-next-line max-len
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
                parentPath: firstChild.meta.parentPath ?? '',
            },
            isChildren: true,
            props: {
                'position-identifier': matchingTabItemConfig.componentSectionId,
            },
        });
    }

    // @ts-expect-error
    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument,@typescript-eslint/no-unsafe-member-access
    router.addRoutes(router.options.routes);
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
    const { routes } = router.options!;

    // Get the deepest matching route
    const deepestMatchingRoute = findDeepestMatchingRoute(routes, (route) => {
        return dynamicPath.startsWith(route.path);
    });

    return deepestMatchingRoute;
}

/* istanbul ignore next */
function findDeepestMatchingRoute(
    routes: RouteConfig[],
    conditionCheck: (route: RouteConfig) => boolean,
): RouteConfig|undefined {
    const matchingRoute = routes.find((route) => {
        return conditionCheck(route);
    });

    if (!matchingRoute) {
        return undefined;
    }

    if (matchingRoute.children && matchingRoute.children.length > 0) {
        return findDeepestMatchingRoute(matchingRoute.children, conditionCheck) ?? matchingRoute;
    }

    return matchingRoute;
}
