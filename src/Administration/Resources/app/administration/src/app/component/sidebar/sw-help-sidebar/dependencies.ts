/**
 * @sw-package framework
 *
 * Everything sw-help-sidebar needs from outside its mini-module.
 */
import { adminHelpCenterStoreId } from 'src/app/store/admin-help-center';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { classifyPlatform, formatShortcutKey } from 'src/core/helper/shortcut-key.helper';
/** @private */
export type { ShortcutKeyLabel } from 'src/core/helper/shortcut-key.helper';
/** @private */
export { shortcutService } from 'src/app/service-names';

/** @private */
export const useAdminHelpCenterStore = () => Shopware.Store.get(adminHelpCenterStoreId);
