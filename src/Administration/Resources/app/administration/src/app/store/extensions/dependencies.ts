/**
 * @sw-package framework
 *
 * Everything extensions store needs from outside its mini-module.
 */
import { acl } from 'src/app/service-names';

/** @private */
export type { privileges } from '@shopware-ag/meteor-admin-sdk/es/_internals/privileges';
/** @private */
export { setExtensions } from '@shopware-ag/meteor-admin-sdk/es/channel';

/** @private */
export const getAcl = () => Shopware.Service(acl);

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
