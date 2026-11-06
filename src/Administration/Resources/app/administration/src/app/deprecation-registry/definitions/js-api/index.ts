/**
 * @sw-package framework
 */

import metaInfoObject from './meta-info-object';
import shopwareAppsStoreSetAppModules from './shopware-apps-store-set-app-modules';
import swProductDetailVariantsLoadConfigSettingGroups from './sw-product-detail-variants-load-config-setting-groups';
import swSelectBaseComputePath from './sw-select-base-compute-path';
import compositionExtensionApi from './composition-extension-api';
import vuexState from './vuex-state';
import orderDetailLoading from './order-detail-loading';
import productStates from './product-states';
import systemConfigSchema from './system-config-schema';
import axiosCancelToken from './axios-cancel-token';
import productAdvancedMode from './product-advanced-mode';
import vite8Config from './vite-8-config';
import helpSidebar from './help-sidebar';
import textEditorOverrides from './text-editor-overrides';
import popoverOverrides from './popover-overrides';
import customFieldSetLoaders from './custom-field-set-loaders';
import ruleConditions from './rule-conditions';
import automaticPromotionToggle from './automatic-promotion-toggle';
import unusedOrderComponents from './unused-order-components';
import orderDetailShippingCosts from './order-detail-shipping-costs';
import workerNotification from './worker-notification';
import agenticCommerceUi from './agentic-commerce-ui';
import mailTemplatePreview from './mail-template-preview';
import mediaGridOutsideClick from './media-grid-outside-click';
import cmsHelpers from './cms-helpers';
import settingsProductPromotionMembers from './settings-product-promotion-members';
import changedDefaults from './changed-defaults';
import dateFilterComputed from './date-filter-computed';
import assetFilterComputed from './asset-filter-computed';
import type { JsApiMigration } from '../helpers';

export default [
    metaInfoObject,
    shopwareAppsStoreSetAppModules,
    swProductDetailVariantsLoadConfigSettingGroups,
    swSelectBaseComputePath,
    compositionExtensionApi,
    vuexState,
    orderDetailLoading,
    productStates,
    systemConfigSchema,
    axiosCancelToken,
    productAdvancedMode,
    vite8Config,
    helpSidebar,
    textEditorOverrides,
    popoverOverrides,
    customFieldSetLoaders,
    ruleConditions,
    automaticPromotionToggle,
    unusedOrderComponents,
    orderDetailShippingCosts,
    workerNotification,
    agenticCommerceUi,
    mailTemplatePreview,
    mediaGridOutsideClick,
    cmsHelpers,
    settingsProductPromotionMembers,
    changedDefaults,
    dateFilterComputed,
    assetFilterComputed,
] satisfies JsApiMigration[];
