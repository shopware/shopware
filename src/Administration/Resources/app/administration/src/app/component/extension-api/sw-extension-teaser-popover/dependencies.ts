/**
 * @sw-package innovation
 *
 * Everything sw-extension-teaser-popover needs from outside its mini-module.
 */
import { teaserPopoverStoreId } from 'src/app/store/teaser-popover.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';

/** @private */
export const useTeaserPopoverStore = () => Shopware.Store.get(teaserPopoverStoreId);
