/**
 * @sw-package framework
 *
 * Everything sw-page needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error.store';
import { sessionStoreId } from 'src/app/store/session.store';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getScrollbarWidth = ((...args: unknown[]) =>
    (Shopware.Utils.dom.getScrollbarWidth as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.dom.getScrollbarWidth;
