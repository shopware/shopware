/**
 * @package admin
 */

// eslint-disable-next-line import/no-named-default
import type { Router, RouteRecordRaw } from 'vue-router';

/**
 * @private
 */
export default function initializeTabs(): void {
    Shopware.ExtensionAPI.handle('uiTabsAddTabItem', async (componentConfig) => {
        Shopware.State.commit('tabs/addTabItem', componentConfig);

        // Reload current route if it does not exist
        const router = Shopware.Application.view?.router as Router;
        const currentRoute = router.currentRoute.value;

        if (!router.hasRoute(currentRoute.name ?? '')) {
            await router.replace(currentRoute.fullPath);
        }
    });

    // Wait until the view is initialized
    void Shopware.Application.viewInitialized.then(() => {
        // Catch non-matching routes, check if they exist in the tabs and create them
        const router = Shopware.Application.view?.router as Router;

        router.beforeResolve((to) => {
            // Skip if route is already registered
            if (router.hasRoute(to.name ?? '')) {
                return;
            }

            // Get all tab routes
            const tabRoutes = Object.values(Shopware.State.get('tabs').tabItems).reduce<string[]>((acc, tabItems) => {
                acc = [...acc, ...tabItems.map((tabItem) => tabItem.componentSectionId)];
                return acc;
            }, []);

            // Check if route contains componentSectionId from the tabs in the path
            const matchingTabRoute = tabRoutes.find((tabRoute) => {
                // Check if the route contains the tabRoute
                return to.fullPath.endsWith(tabRoute);
            });

            if (!matchingTabRoute) {
                return;
            }

            // Check if the route is already registered
            if (router.hasRoute(to.fullPath)) {
                return;
            }


            // Get the parent route
            const parentPath = to.fullPath.replace(matchingTabRoute, '');
            const parentRoute = router.resolve(parentPath);

            // Get the new route name based on parent route name
            const newRouteName = `${parentRoute.name as string}.${matchingTabRoute}`;

            // Get the new path based on the parent path last match
            const lastMatchingParent = parentRoute.matched?.[parentRoute.matched.length - 1];
            const newPath = `${lastMatchingParent?.path}/${matchingTabRoute}`;

            // Get the $module information from parent and add it to the new route in meta
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-call
            const moduleInfo = Shopware?.Application?.$container?.container?.init?.router?.getModuleInfo?.(parentRoute) as {
                manifest: Record<string, unknown>;
            }|undefined;

            // Create a new route for the tab
            const newRoute: RouteRecordRaw = {
                path: newPath,
                component: Shopware.Application.view?.getComponent('sw-extension-component-section'),
                children: [],
                name: newRouteName,
                meta: {
                    parentPath: parentRoute?.meta?.parentPath ?? parentRoute.name,
                },
                props: {
                    'position-identifier': matchingTabRoute,
                },
            };

            if (moduleInfo?.manifest) {
                newRoute.meta!.$module = moduleInfo?.manifest;
            }

            // Add the new route to the router
            router.addRoute(parentRoute.name ?? '', newRoute);

            // Reload current route after adding the new route
            // eslint-disable-next-line consistent-return
            return { path: to.fullPath };
        });
    });
}
