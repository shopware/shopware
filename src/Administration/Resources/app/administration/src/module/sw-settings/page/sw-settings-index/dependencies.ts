/**
 * @sw-package framework
 *
 * Everything sw-settings-index needs from outside its mini-module.
 */
import { userConfigService } from 'src/app/service-names';
import { settingsItemsStoreId } from 'src/app/store/settings-item';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { hasOwnProperty } from 'src/core/service/utils/object.utils';
/** @private */
export { acl as aclService, feature as featureService } from 'src/app/service-names';
/** @private */
export type { SettingsItem } from 'src/app/store/settings-item';

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);

/** @private */
export const useSettingsItemsStore = () => Shopware.Store.get(settingsItemsStoreId);

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
