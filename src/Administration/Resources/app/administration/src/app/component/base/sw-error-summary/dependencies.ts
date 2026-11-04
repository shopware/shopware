/**
 * @sw-package framework
 *
 * Everything sw-error-summary needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const hasOwnProperty = ((...args: unknown[]) =>
    (Shopware.Utils.object.hasOwnProperty as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.object.hasOwnProperty;
