/**
 * @sw-package framework
 * @private
 */

interface EntryRouteConfig {
    extensionName: string;
    route: string;
    label?: string;
}

const extensionEntryRoutes = Shopware.Store.register({
    id: 'extensionEntryRoutes',

    state: () => ({
        routes: {} as Record<string, EntryRouteConfig>,
    }),

    actions: {
        addItem(config: EntryRouteConfig) {
            this.routes[config.extensionName] = config;
        },
    },
});

/**
 * @private
 */
export type ExtensionEntryRoutes = ReturnType<typeof extensionEntryRoutes>;

/**
 * @private
 */
export default extensionEntryRoutes;
