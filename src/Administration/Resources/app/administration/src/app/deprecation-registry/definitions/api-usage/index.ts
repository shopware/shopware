/**
 * @sw-package framework
 */

import type { ApiUsageMigration } from '../helpers';
import agenticCommerce from './agentic-commerce';
import automaticPromotions from './automatic-promotions';
import axios from './axios';
import changedDefaults from './changed-defaults';
import cmsHelpers from './cms-helpers';
import compositionExtensionApi from './composition-extension-api';
import customFieldSetLoaders from './custom-field-set-loaders';
import filterComputeds from './filter-computeds';
import mailTemplatePreview from './mail-template-preview';
import mediaGridOutsideClick from './media-grid-outside-click';
import moduleSnippets from './module-snippets';
import orderDetailLoading from './order-detail-loading';
import orderListHelpers from './order-list-helpers';
import orderShippingCosts from './order-shipping-costs';
import productAdvancedMode from './product-advanced-mode';
import productStates from './product-states';
import removedComponents from './removed-components';
import ruleConditions from './rule-conditions';
import serviceMethods from './service-methods';
import settingsProductPromotionMembers from './settings-product-promotion-members';
import systemConfig from './system-config';
import unusedOrderComponents from './unused-order-components';
import vite8 from './vite-8';
import vuex from './vuex';
import workerNotification from './worker-notification';

export default [
    moduleSnippets,
    compositionExtensionApi,
    vuex,
    ...filterComputeds,
    orderListHelpers,
    ...removedComponents,
    orderDetailLoading,
    productStates,
    systemConfig,
    axios,
    productAdvancedMode,
    vite8,
    ...serviceMethods,
    customFieldSetLoaders,
    ruleConditions,
    automaticPromotions,
    unusedOrderComponents,
    orderShippingCosts,
    workerNotification,
    agenticCommerce,
    mailTemplatePreview,
    mediaGridOutsideClick,
    cmsHelpers,
    settingsProductPromotionMembers,
    changedDefaults,
] satisfies ApiUsageMigration[];
