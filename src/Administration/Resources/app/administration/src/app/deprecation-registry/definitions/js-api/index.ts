/**
 * @sw-package framework
 */

import metaInfoObject from './meta-info-object';
import shopwareAppsStoreSetAppModules from './shopware-apps-store-set-app-modules';
import swProductDetailVariantsLoadConfigSettingGroups from './sw-product-detail-variants-load-config-setting-groups';
import swSelectBaseComputePath from './sw-select-base-compute-path';
import type { JsApiMigration } from '../helpers';

export default [
    metaInfoObject,
    shopwareAppsStoreSetAppModules,
    swProductDetailVariantsLoadConfigSettingGroups,
    swSelectBaseComputePath,
] satisfies JsApiMigration[];
