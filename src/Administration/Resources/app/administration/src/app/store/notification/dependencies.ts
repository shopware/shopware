/**
 * @sw-package framework
 *
 * Everything notification store needs from outside its mini-module.
 */
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { POLL_BACKGROUND_INTERVAL } from 'src/core/worker/worker-notification-listener';
/** @private */
export { useSessionStore } from 'src/app/store/session';

/** @private */
export const warn = ((...args: unknown[]) =>
    (Shopware.Utils.debug.warn as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.warn;

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const hasStore = () => !!Shopware.Store;

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
