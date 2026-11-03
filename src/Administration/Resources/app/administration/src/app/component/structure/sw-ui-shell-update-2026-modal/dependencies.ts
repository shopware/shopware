/**
 * @sw-package framework
 *
 * Everything sw-ui-shell-update-2026-modal needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { userConfigService } from 'src/app/service-names';
import { contextStoreId } from 'src/app/store/context.store';
import { sessionStoreId } from 'src/app/store/session.store';

/** @private */
export { MtBadge, MtModal, MtModalRoot } from '@shopware-ag/meteor-component-library';
/** @private */
export type { Theme } from '@shopware-ag/meteor-component-library';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as useTheme } from 'src/app/composables/use-theme';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
