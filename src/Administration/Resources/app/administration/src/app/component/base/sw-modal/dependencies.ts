/**
 * @sw-package framework
 *
 * Everything sw-modal needs from outside its mini-module.
 */
import { adminHelpCenterStoreId } from 'src/app/store/admin-help-center.store';

/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { shortcutService } from 'src/app/service-names';

/** @private */
export const useAdminHelpCenterStore = () => Shopware.Store.get(adminHelpCenterStoreId);
