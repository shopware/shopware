/**
 * @sw-package framework
 *
 * Everything adminMenu store needs from outside its mini-module.
 */
import { menuService } from 'src/app/service-names';

/** @private */
export type { ModuleManifest } from 'src/core/factory/module.factory';
/** @private */
export type { AppModuleDefinition } from 'src/core/service/api/app-modules.service';
/** @private */
export { useShopwareAppsStore } from 'src/app/store/shopware-apps';

/** @private */
export const getMenuService = (): ServiceContainer['menuService'] => Shopware.Service(menuService);

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
