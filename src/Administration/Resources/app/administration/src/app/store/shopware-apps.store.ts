/**
 * @sw-package framework
 */
import type { AppModuleDefinition } from 'src/core/service/api/app-modules.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface ShopwareAppsState {
    apps: AppModuleDefinition[];
    selectedIds: string[];
}

const shopwareApps = Shopware.Store.register({
    id: 'shopwareApps',

    state: (): {
        apps: AppModuleDefinition[];
        selectedIds: string[];
    } => ({
        apps: [],
        selectedIds: [],
    }),
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type ShopwareApps = ReturnType<typeof shopwareApps>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default shopwareApps;
