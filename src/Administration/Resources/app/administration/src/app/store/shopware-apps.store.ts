/**
 * @sw-package framework
 */
import type { AppModuleDefinition } from 'src/core/service/api/app-modules.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface ShopwareAppsState {
    apps: AppModuleDefinition[];
    appsLoaded: boolean;
    selectedIds: string[];
}

/**
 * @private
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const shopwareAppsStoreId = 'shopwareApps';

const shopwareApps = Shopware.Store.register({
    id: shopwareAppsStoreId,

    state: (): {
        apps: AppModuleDefinition[];
        appsLoaded: boolean;
        selectedIds: string[];
    } => ({
        apps: [],
        /**
         * Whether `apps` reflects a finished fetch — an empty list is ambiguous otherwise
         */
        appsLoaded: false,
        selectedIds: [],
    }),
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type ShopwareApps = ReturnType<typeof shopwareApps>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default shopwareApps;
