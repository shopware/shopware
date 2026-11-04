/**
 * @sw-package discovery
 *
 * Everything sw-media-quickinfo-multiple needs from outside its mini-module.
 */
import { actionButtonsStoreId } from 'src/app/store/action-buttons.store';

/** @private */
export { default as mediaSidebarModalMixinMixin } from '../../../mixin/media-sidebar-modal-mixin/private'; // eslint-disable-line sw-core-rules/mini-module-boundaries -- the mixin is private, extensions reach it with Mixin.getByName()

/** @private */
export const useActionButtonsStore = () => Shopware.Store.get(actionButtonsStoreId);
