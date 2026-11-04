/**
 * @sw-package after-sales
 *
 * Everything sw-dashboard-index needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);
