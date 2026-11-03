/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-plugin-card needs from outside its mini-module.
 */
import { truncateFilterName } from 'src/app/filter/truncate.filter';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { cacheApiService, extensionHelperService, shopwareExtensionService } from 'src/app/service-names';
/** @private */
export { default as swExtensionErrorMixin } from 'src/module/sw-extension/mixin/sw-extension-error.mixin';
/** @private */
export type { ExtensionType } from 'src/module/sw-extension/service/extension-store-action.service';

/** @private */
export const getTruncateFilter = () => Shopware.Filter.getByName(truncateFilterName);
