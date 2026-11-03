/**
 * @sw-package framework
 *
 * Everything sw-error-summary needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const hasOwnProperty = (...args: Parameters<typeof Shopware.Utils.object.hasOwnProperty>) =>
    Shopware.Utils.object.hasOwnProperty(...args);
