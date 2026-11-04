/**
 * @sw-package framework
 *
 * Everything sw-help-center-v2 needs from outside its mini-module.
 */
import { adminHelpCenterStoreId } from 'src/app/store/admin-help-center';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';

/** @private */
export const useAdminHelpCenterStore = () => Shopware.Store.get(adminHelpCenterStoreId);
