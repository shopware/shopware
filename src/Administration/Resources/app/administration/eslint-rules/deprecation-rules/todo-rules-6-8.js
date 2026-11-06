/**
 * @sw-package framework
 *
 * Codemods for Administration changes of Shopware 6.8 that cannot be rewritten automatically.
 * Each rule writes a `TODO Codemod:` comment with the replacement above every affected spot,
 * see utils/todo-rule.js for the matchers. The keys are the rule names.
 */

const { createTodoRule } = require('./utils/todo-rule');

/**
 * Matchers for members a component loses: reads via `this`, definitions in an override and
 * uses in its templates, all limited to overrides and extensions of the components.
 *
 * @param {string[]} components
 * @param {string[]} names
 * @param {string} hint
 */
function members(components, names, hint) {
    return {
        script: [
            { type: 'member', object: 'this', components, properties: names, hint },
            { type: 'option', components, names, hint },
        ],
        template: [
            { type: 'identifier', components, names, hint },
        ],
    };
}

/**
 * @param {string} description
 * @param {...{ script?: object[], template?: object[] }} parts
 */
function todoRule(description, ...parts) {
    return createTodoRule({
        description,
        script: parts.flatMap((part) => part.script ?? []),
        template: parts.flatMap((part) => part.template ?? []),
    });
}

/**
 * Overrides, extensions and tags of components that are removed.
 *
 * @param {string[]} names
 * @param {string} hint
 */
function removedComponents(names, hint) {
    return {
        script: [{ type: 'component', names, hint }],
        template: [{ type: 'element', names, hint }],
    };
}

const REMOVED = 'It is removed in 6.8 without replacement.';

const COMPOSITION_HINT = 'This Composition API extension system function is private in 6.8. ' +
    'Write a native setup override (<component>.override.vue with swDefineOverride()) or keep Component.override().';
const COMPOSITION_FUNCTIONS = [
    'createExtendableSetup',
    'attachOverrides',
    'getExposedProps',
    'overrideComponentSetup',
    'registerNativeExtensionTargets',
    'registerOverrideComponent',
    'getOverrideComponents',
];

const VUEX_HINT = 'Vuex is removed in 6.8. Use Pinia stores: Shopware.Store.get(id) and Shopware.Store.register({ id, state, getters, actions }).';

const TEXT_EDITOR_HINT = 'sw-text-editor is removed in 6.8. Use mt-text-editor with v-model, custom-buttons instead of button-config, ' +
    'and createTextEditorDataMappingButton() of the component helper for data mapping.';
const TEXT_EDITOR_TOOLBAR_HINT = 'The sw-text-editor toolbar components are removed in 6.8. Add buttons with the custom-buttons prop of mt-text-editor.';

const ORDER_DETAIL_HINT = 'Order detail loading and editing moved to the swOrderDetail store in 6.8. ' +
    'Use Shopware.Store.get(\'swOrderDetail\'): loading.order, setLoading([\'order\', value]), editing, savedSuccessful and saveAndReload().';
const ORDER_DETAIL_VIEWS = ['sw-order-detail-general', 'sw-order-detail-details'];

const PRODUCT_STATES_HINT = 'Product states are removed in 6.8. ' +
    'Use product.type or productType (\'physical\' or \'digital\') and creationType instead.';

const SYSTEM_CONFIG_HINT = 'sw-system-config no longer has config and its config/schema sync helpers in 6.8. ' +
    'Use schema, a list of tabs with their cards.';

const CANCEL_TOKEN_HINT = 'axios 1.x is the default HTTP client in 6.8. Replace the CancelToken with an AbortController and pass its signal.';
const IS_CANCEL_HINT = 'axios 1.x is the default HTTP client in 6.8. Detect a cancelled request with httpClient.isCancel(error).';

const ADVANCED_MODE_HINT = 'The product detail advanced mode is removed in 6.8 without replacement. ' +
    'Stop extending the mode settings and show your card without showProductCard().';

const VITE_HINT = 'Shopware 6.8 builds the Administration with Vite 8 (Rolldown, Oxc, Node ^20.19 or >=22.12). ' +
    'Migrate this config with the Vite 7 and Vite 8 migration guides.';

const POPOVER_HINT = 'sw-popover is removed in 6.8. Use mt-floating-ui: the reference element goes into #trigger, bind :is-opened, ' +
    'resize-width becomes match-reference-width, and floating-ui-options replaces z-index and popover-config-extension.';

const CUSTOM_FIELD_SETS_HINT = 'The custom field set loaders are removed in 6.8. ' +
    'Load the sets with Shopware.Service(\'customFieldDataProviderService\').getCustomFieldSets(entityName).';

const RULE_CONDITION_HINT = 'This rule condition component is removed in 6.8. ' +
    'Use sw-condition-generic (sw-condition-generic-line-item for line items), it renders the condition from its backend config.';
const RULE_CONDITIONS = [
    'sw-condition-line-item-in-category',
    'sw-condition-line-item-purchase-price',
    'sw-condition-is-net-select',
];

const AUTOMATIC_PROMOTIONS_HINT = 'Automatic promotions can no longer be switched off in an order in 6.8. ' +
    'Use applyAutomaticPromotions() of sw-order-promotion-field or orderService.applyAutomaticPromotions(orderId, versionId).';

const UNUSED_ORDER_COMPONENT_HINT = `Core no longer uses this order component. ${REMOVED} Remove this override or usage.`;

const SHIPPING_COSTS_HINT = 'Shipping costs are edited in sw-order-detail-general in 6.8: ' +
    'block sw_order_detail_general_line_items_summary_shipping_cost and onShippingChargeUpdated(amount).';

const WORKER_NOTIFICATION_HINT = 'Increment-based message queue notifications are removed in 6.8 without replacement. ' +
    'Remove this WorkerNotification middleware.';

const AGENTIC_COMMERCE_HINT = 'The Agentic Commerce Administration UI moves to the SwagAgenticCommerce plugin in 6.8. ' +
    'Install it and remove this override.';
const AGENTIC_COMMERCE_MEMBERS = {
    'sw-sales-channel-detail': [
        'agenticCommerceExportConfig',
        'isAgenticCommerce',
        'hasSwagAgenticCommercePlugin',
        'showAgenticCommerceDeprecationBanner',
        'defaultAgenticCommerceExportConfig',
        'onClickInstallAgenticCommercePlugin',
        'validateAgenticCommerceExportConfig',
        'loadAgenticCommerceExportConfig',
        'saveAgenticCommerceExportConfig',
    ],
    'sw-sales-channel-detail-base': [
        'isAgenticCommerce',
        'resolvedAgenticCommerceExportConfig',
        'getAgenticCommerceExportElementBind',
        'getAgenticCommerceExportCardTitle',
        'getAgenticCommerceExportCardPositionIdentifier',
        'onAgenticCommerceExportFieldUpdate',
    ],
    'sw-sales-channel-modal-grid': ['isAgenticCommerceSalesChannelType', 'showAgenticCommerceType'],
    'sw-sales-channel-detail-product-comparison': ['isAgenticCommerce'],
    'sw-sales-channel-create-base': ['prefillAgenticCommerceDefaults'],
};

const MAIL_PREVIEW_HINT = 'The mail template preview moved to sw-mail-template-preview-modal in 6.8. ' +
    'It previews with mailService.previewMailTemplate() and sends test mails with sendMailTemplate() in test mode.';

const MEDIA_GRID_HINT = 'sw-media-grid no longer clears the selection on an outside click in 6.8. ' +
    'Listen to media-item-selection-clear of sw-media-quickinfo-multiple instead.';

/** Members of settings, product and promotion components, each `[components, members, hint]`. */
const SETTINGS_PRODUCT_PROMOTION_MEMBERS = [
    [['sw-settings-index'], ['hideSettingRenameBanner', 'getUserConfig', 'onCloseSettingRenameBanner'], `The settings rename banner is removed in 6.8. ${REMOVED}`],
    [['sw-settings-customer-group-detail'], ['seoUrlRepository', 'seoUrlCriteria', 'loadSeoUrls', 'getSeoUrl'], `The SEO URLs are built in the template in 6.8. ${REMOVED}`],
    [['sw-settings-number-range-detail'], ['selectedNumberRangeSalesChannels'], 'It is removed in 6.8. Use selectedSalesChannelsCollection.'],
    [['sw-settings-number-range-detail'], ['loadSalesChannels'], 'It is removed in 6.8. Use buildSelectedSalesChannelsCollection().'],
    [['sw-settings-shipping-price-matrix'], ['shippingRuleFilterCriteria'], 'It is removed in 6.8. Use ruleFilterCriteria.'],
    [['sw-settings-listing-option-base'], ['productSortingEntityCriteria'], REMOVED],
    [['sw-settings-language-list'], ['filterSidebarItem', 'registerFilterSidebarItem', 'openFilterSidebar'], REMOVED],
    [['sw-settings-snippet-set-list'], ['onClone', 'closeCloneModal', 'getNoPermissionsTooltip'], REMOVED],
    [['sw-settings-snippet-list'], ['inlineSaveSuccessMessage', 'createSuccessMessage'], REMOVED],
    [['sw-settings-cache-index'], ['processSuccess', 'resetButtons'], REMOVED],
    [
        ['sw-settings-document-detail'],
        [
            'selectedType',
            'isSaveSuccessful',
            'isShowCountriesSelect',
            'countryRepository',
            'documentTypeRepository',
            'documentBaseConfigSalesChannelRepository',
            'loadAvailableSalesChannel',
            'showOption',
        ],
        REMOVED,
    ],
    [['sw-settings-document-list'], ['sortBy', 'expandButtonClass', 'collapseButtonClass'], REMOVED],
    [['sw-product-cross-selling-form'], ['productCrossSellingRepository', 'crossSellingAssigmentRepository', 'disablePositioning'], REMOVED],
    [['sw-product-clone-modal', 'sw-product-modal-variant-generation'], ['progressInPercentage'], REMOVED],
    [['sw-product-packaging-form'], ['isLoading'], REMOVED],
    [['sw-product-list'], ['productTypeOptions'], REMOVED],
    [['sw-product-detail-variants'], ['loadConfigSettingGroups'], REMOVED],
    [
        ['sw-promotion-v2-cart-condition-form', 'sw-promotion-discount-component'],
        ['ruleFilter'],
        'It is removed in 6.8. Remove the :rule-filter binding, sw-select-rule-create filters the rules by default.',
    ],
    [
        ['sw-promotion-v2-settings-rule-selection', 'sw-promotion-v2-settings-trigger'],
        ['ruleCriteria'],
        'It is removed in 6.8. Remove the :rule-filter binding, sw-select-rule-create filters the rules by default.',
    ],
    [
        ['sw-promotion-v2-conditions'],
        ['personaRuleFilter', 'cartConditionsRuleFilter', 'orderConditionsFilter'],
        'It is removed in 6.8. Remove the :rule-filter binding, sw-select-rule-create filters the rules by default.',
    ],
    [['sw-promotion-discount-component'], ['maxValueAdvancedPricesTooltip'], REMOVED],
    [['sw-promotion-detail-discounts'], ['deleteDiscountId', 'repository'], REMOVED],
];

/** Whether a `sendMailTemplate()` call leaves out the `templateData` argument. */
function sendsWithoutTemplateData(member) {
    const call = member.parent;

    if (call.type !== 'CallExpression' || call.callee !== member) {
        return false;
    }

    const templateData = call.arguments[7];

    return !templateData
        || (templateData.type === 'Literal' && templateData.value === null)
        || (templateData.type === 'Identifier' && templateData.name === 'undefined');
}

module.exports = {
    'no-private-composition-extension-api': todoRule(
        'Comment the Composition API extension system functions that are private in Shopware 6.8',
        {
            script: [
                { type: 'member', object: 'Shopware.Component', properties: COMPOSITION_FUNCTIONS, hint: COMPOSITION_HINT },
                { type: 'member', object: 'Component', properties: COMPOSITION_FUNCTIONS, hint: COMPOSITION_HINT },
                {
                    type: 'import',
                    source: ['src/app/adapter/composition-extension-system', 'src/core/factory/native-extension-targets'],
                    hint: COMPOSITION_HINT,
                },
            ],
        },
    ),

    'no-vuex-state': todoRule('Comment the Vuex APIs that Shopware 6.8 removes', {
        script: [
            { type: 'member', object: 'Shopware', properties: ['State'], hint: VUEX_HINT },
            { type: 'member', object: 'this', properties: ['$store'], hint: VUEX_HINT },
            {
                type: 'member',
                properties: ['mapVuexState', 'mapVuexMutations', 'mapVuexGetters', 'mapVuexActions'],
                hint: `${VUEX_HINT} Map them with mapState and mapActions of the component helper.`,
            },
            { type: 'import', source: ['vuex'], hint: VUEX_HINT },
        ],
        template: [
            { type: 'identifier', names: ['$store'], hint: VUEX_HINT },
        ],
    }),

    'no-sw-text-editor': todoRule(
        'Comment sw-text-editor and its toolbar components that Shopware 6.8 removes',
        removedComponents(['sw-text-editor'], TEXT_EDITOR_HINT),
        removedComponents([
            'sw-text-editor-toolbar',
            'sw-text-editor-toolbar-button',
            'sw-text-editor-toolbar-table-button',
            'sw-text-editor-table-toolbar',
            'sw-text-editor-link-menu',
        ], TEXT_EDITOR_TOOLBAR_HINT),
    ),

    'no-order-detail-loading-members': todoRule(
        'Comment the order detail loading and editing members that Shopware 6.8 moves to the swOrderDetail store',
        {
            script: [
                {
                    type: 'inject',
                    names: ['swOrderDetailOnLoadingChange', 'swOrderDetailOnEditingChange', 'swOrderDetailOnCreatedByIdChange'],
                    hint: ORDER_DETAIL_HINT,
                },
            ],
            template: [
                {
                    type: 'attribute',
                    elements: ['router-view'],
                    names: ['is-editing', 'is-save-successful', '@created-by-id-change', '@loading-change', '@editing-change'],
                    hint: ORDER_DETAIL_HINT,
                },
                { type: 'attribute', elements: ['sw-order-promotion-field'], names: ['@loading-change'], hint: ORDER_DETAIL_HINT },
            ],
        },
        members(['sw-order-detail'], [
            'isEditing',
            'isLoading',
            'createdById',
            'updateCreatedById',
            'onChangeLanguage',
            'onStartEditing',
            'onSaveAndReload',
            'onUpdateLoading',
            'onUpdateEditing',
        ], ORDER_DETAIL_HINT),
        members(ORDER_DETAIL_VIEWS, ['isLoading', 'isSaveSuccessful', 'updateLoading'], ORDER_DETAIL_HINT),
    ),

    'no-product-states': todoRule(
        'Comment the product states that Shopware 6.8 replaces with the product type',
        members([
            'sw-product-detail',
            'sw-product-detail-base',
            'sw-product-detail-specifications',
            'sw-product-variants-overview',
        ], ['productStates'], PRODUCT_STATES_HINT),
        members(['sw-product-detail-variants'], ['currentProductStates'], PRODUCT_STATES_HINT),
        members(['sw-product-stream-value'], ['productStateOptions'], PRODUCT_STATES_HINT),
        {
            script: [
                { type: 'member', store: 'swProductDetail', properties: ['productStates', 'creationStates'], hint: PRODUCT_STATES_HINT },
                { type: 'member', properties: ['creationStates'], hint: PRODUCT_STATES_HINT },
                { type: 'member', object: 'product', properties: ['states'], hint: PRODUCT_STATES_HINT },
                { type: 'member', object: 'this.product', properties: ['states'], hint: PRODUCT_STATES_HINT },
                { type: 'string', values: ['is-physical', 'is-download', 'product-states-filter'], hint: PRODUCT_STATES_HINT },
            ],
            template: [
                { type: 'attribute', elements: ['sw-product-variants-overview'], names: ['product-states'], hint: PRODUCT_STATES_HINT },
            ],
        },
    ),

    'no-system-config-config': todoRule(
        'Comment the config members of sw-system-config and systemConfigApiService.getConfig() that Shopware 6.8 removes',
        members(['sw-system-config'], [
            'config',
            'isSyncingFromSchema',
            'isSyncingFromConfig',
            'schemaToConfig',
            'configToSchema',
            'assignConfigIds',
            'getSchemaForDomain',
            'getTabIndexByElementOverlap',
        ], SYSTEM_CONFIG_HINT),
        {
            script: [
                {
                    type: 'member',
                    service: 'systemConfigApiService',
                    properties: ['getConfig'],
                    hint: 'systemConfigApiService.getConfig() is removed in 6.8. Use getSchema(domain), and decorate getSchema instead of getConfig.',
                },
            ],
        },
    ),

    'no-axios-cancel-token': todoRule(
        'Comment the axios 0.x cancellation APIs that the default axios 1.x client of Shopware 6.8 does not support',
        {
            script: [
                { type: 'member', properties: ['CancelToken', '__CANCEL__'], hint: CANCEL_TOKEN_HINT },
                { type: 'key', names: ['cancelToken'], hint: CANCEL_TOKEN_HINT },
                {
                    type: 'import',
                    source: ['axios', 'src/core/factory/http.factory'],
                    specifiers: ['CancelToken', 'Cancel', 'isCancel'],
                    hint: `${CANCEL_TOKEN_HINT} Detect a cancelled request with httpClient.isCancel(error).`,
                },
                { type: 'member', object: 'axios', properties: ['isCancel', 'Cancel'], hint: IS_CANCEL_HINT },
                {
                    type: 'key',
                    names: ['useAxiosV1'],
                    hint: 'axios 1.x is the default HTTP client in 6.8. Remove useAxiosV1 and migrate the request to axios 1.x.',
                },
            ],
        },
    ),

    'no-product-advanced-mode': todoRule(
        'Comment the product detail advanced mode that Shopware 6.8 removes',
        members(['sw-product-detail'], [
            'advancedModeSetting',
            'getModeSettingGeneralTab',
            'getModeSettingSpecificationsTab',
            'showAdvanceModeSetting',
            'initAdvancedModeSettings',
            'createUserModeSetting',
            'getAdvancedModeDefaultSetting',
            'getAdvancedModeSetting',
            'saveAdvancedMode',
            'onChangeSetting',
            'changeModeSettings',
            'onChangeSettingItem',
            'advanceModeEnabled',
        ], ADVANCED_MODE_HINT),
        removedComponents(['sw-product-settings-mode'], ADVANCED_MODE_HINT),
        {
            script: [
                {
                    type: 'member',
                    store: 'swProductDetail',
                    properties: ['advancedModeSetting', 'showModeSetting', 'advanceModeEnabled'],
                    hint: ADVANCED_MODE_HINT,
                },
                { type: 'member', properties: ['advanceModeEnabled'], hint: ADVANCED_MODE_HINT },
                { type: 'string', values: ['mode.setting.advancedModeSettings'], hint: ADVANCED_MODE_HINT },
            ],
        },
    ),

    'no-vite-7-config': todoRule('Comment build configs of extensions that need a migration to Vite 8', {
        script: [
            { type: 'file', pattern: /^vite\.config\.[cm]?[jt]s$/, hint: VITE_HINT },
            {
                type: 'key',
                names: ['rollupOptions', 'esbuildOptions', 'commonjsOptions'],
                hint: 'Vite 8 bundles with Rolldown: rename rollupOptions to rolldownOptions and optimizeDeps.esbuildOptions to ' +
                    'optimizeDeps.rolldownOptions, build.commonjsOptions has no effect.',
            },
        ],
    }),

    'no-sw-help-sidebar': todoRule(
        'Comment sw-help-sidebar that Shopware 6.8 removes',
        removedComponents(
            ['sw-help-sidebar'],
            'sw-help-sidebar is removed in 6.8. The help center lives in sw-help-center-v2, which has the same block names ' +
                'but renders mt-action-menu-item elements. Move your override there or remove it.',
        ),
    ),

    'no-sw-popover': todoRule(
        'Comment sw-popover that Shopware 6.8 removes in favor of mt-floating-ui',
        removedComponents(['sw-popover'], POPOVER_HINT),
        {
            template: [
                { type: 'block', names: ['sw_popover_deprecated'], hint: POPOVER_HINT },
            ],
        },
    ),

    'no-custom-field-set-loaders': todoRule(
        'Comment the custom field set loaders that Shopware 6.8 removes',
        members([
            'sw-category-detail',
            'sw-customer-detail-base',
            'sw-manufacturer-detail',
            'sw-order-detail-details',
            'sw-settings-units-detail',
            'sw-bulk-edit-customer',
            'sw-bulk-edit-order',
            'sw-bulk-edit-product',
        ], ['customFieldSetRepository', 'customFieldSetCriteria'], CUSTOM_FIELD_SETS_HINT),
        members(['sw-category-detail'], ['customFieldSetLandingPageCriteria'], CUSTOM_FIELD_SETS_HINT),
        members(['sw-customer-detail-addresses'], ['customFieldSetRepository'], CUSTOM_FIELD_SETS_HINT),
        members(['sw-sales-channel-detail'], ['customFieldRepository'], CUSTOM_FIELD_SETS_HINT),
    ),

    'no-removed-rule-conditions': todoRule(
        'Comment the rule condition components that Shopware 6.8 replaces with sw-condition-generic',
        removedComponents(RULE_CONDITIONS, RULE_CONDITION_HINT),
        {
            script: [
                { type: 'string', values: RULE_CONDITIONS, hint: RULE_CONDITION_HINT },
            ],
            template: [
                {
                    type: 'block',
                    names: [
                        'sw_condition_line_item_in_category_field_operator',
                        'sw_condition_line_item_in_category_field_identifiers',
                        'sw_condition_line_item_in_category_field_result_item',
                        'sw_condition_line_item_in_category_field_result_item_content',
                        'sw_condition_line_item_in_category_field_description',
                        'sw_condition_line_item_in_category_field_description_default',
                        'sw_condition_line_item_purchase_price_field_gross_net',
                        'sw_condition_line_item_purchase_price_field_operator',
                        'sw_condition_line_item_purchase_price_field_amount',
                    ],
                    hint: RULE_CONDITION_HINT,
                },
            ],
        },
    ),

    'no-automatic-promotion-toggle': todoRule(
        'Comment the automatic promotion toggle of orders that Shopware 6.8 removes',
        members(['sw-order-promotion-field'], [
            'automaticPromotions',
            'hasAutomaticPromotions',
            'changesetGenerator',
            'hasOrderUnsavedChanges',
            'disabledAutoPromotions',
            'createdComponent',
            'emitLoadingChange',
            'handleUnsavedOrderChangesResponse',
            'deleteAutomaticPromotions',
            'toggleAutomaticPromotions',
            'getLineItemByPromotionCode',
        ], AUTOMATIC_PROMOTIONS_HINT),
        {
            script: [
                { type: 'member', service: 'orderService', properties: ['toggleAutomaticPromotions'], hint: AUTOMATIC_PROMOTIONS_HINT },
            ],
        },
    ),

    'no-unused-order-components': todoRule(
        'Comment the unused order create and state history components that Shopware 6.8 removes',
        removedComponents([
            'sw-order-create-base',
            'sw-order-create-details-header',
            'sw-order-create-details-body',
            'sw-order-create-details-footer',
            'sw-order-create-address-modal',
            'sw-order-state-history-card',
            'sw-order-state-history-card-entry',
            'sw-order-user-card',
        ], UNUSED_ORDER_COMPONENT_HINT),
        members(['sw-order-state-history-modal'], ['total'], 'It is removed in 6.8. Use dataSource.length instead.'),
        {
            template: [
                {
                    type: 'attribute',
                    elements: ['sw-order-state-history-modal'],
                    names: ['is-loading'],
                    hint: `sw-order-state-history-modal no longer has the isLoading prop. ${REMOVED}`,
                },
            ],
        },
    ),

    'no-order-detail-shipping-costs': todoRule(
        'Comment the shipping cost editing of sw-order-detail-details that Shopware 6.8 removes',
        members(['sw-order-detail-details'], ['shippingCosts', 'onShippingChargeEdited', 'currency'], SHIPPING_COSTS_HINT),
    ),

    'no-worker-notification': todoRule('Comment the increment-based message queue notifications that Shopware 6.8 removes', {
        script: [
            { type: 'member', object: 'Shopware', properties: ['WorkerNotification'], hint: WORKER_NOTIFICATION_HINT },
            { type: 'member', properties: ['workerNotification', 'enableQueueStatsWorker'], hint: WORKER_NOTIFICATION_HINT },
            {
                type: 'import',
                source: ['src/core/factory/worker-notification.factory', 'src/core/worker/worker-notification-listener'],
                hint: WORKER_NOTIFICATION_HINT,
            },
        ],
    }),

    'no-agentic-commerce-ui': todoRule(
        'Comment the Agentic Commerce Administration UI that Shopware 6.8 moves to SwagAgenticCommerce',
        ...Object.entries(AGENTIC_COMMERCE_MEMBERS).map(([component, names]) => members([component], names, AGENTIC_COMMERCE_HINT)),
        removedComponents(['sw-agentic-commerce-tracking-config'], AGENTIC_COMMERCE_HINT),
        {
            script: [
                { type: 'inject', names: ['swSalesChannelDetailGetAgenticCommerceExportConfig'], hint: AGENTIC_COMMERCE_HINT },
            ],
            template: [
                {
                    type: 'block',
                    names: [
                        'sw_sales_channel_detail_agentic_commerce_deprecation_banner',
                        'sw_sales_channel_detail_agentic_commerce_tracking',
                    ],
                    hint: AGENTIC_COMMERCE_HINT,
                },
            ],
        },
    ),

    'no-mail-template-preview-apis': todoRule(
        'Comment the mail template preview APIs that Shopware 6.8 replaces with sw-mail-template-preview-modal',
        members(['sw-mail-template-detail'], ['hasTemplateData', 'mailPreviewContent', 'replaceContent'], MAIL_PREVIEW_HINT),
        members(['sw-mail-template-index'], ['term', 'getList'], `The mail template lists search on their own in 6.8. ${REMOVED}`),
        {
            script: [
                { type: 'member', properties: ['testMailTemplate', 'buildRenderPreview'], hint: MAIL_PREVIEW_HINT },
            ],
            template: [
                {
                    type: 'attribute',
                    elements: ['sw-mail-template-list', 'sw-mail-header-footer-list'],
                    names: ['search-term'],
                    hint: `The mail template lists no longer have the searchTerm prop. ${REMOVED}`,
                },
            ],
        },
    ),

    'no-media-grid-outside-click': todoRule(
        'Comment the outside click deselection of sw-media-grid that Shopware 6.8 removes',
        members(['sw-media-grid'], [
            'nonDeselectingComponents',
            'createdComponent',
            'beforeDestroyComponent',
            'clearSelectionOnClickOutside',
            'originatesFromExcludedComponent',
            'isEmittedFromChildren',
            'emitSelectionCleared',
        ], MEDIA_GRID_HINT),
        {
            template: [
                { type: 'attribute', elements: ['sw-media-grid'], names: ['@media-grid-selection-clear'], hint: MEDIA_GRID_HINT },
            ],
        },
    ),

    'no-cms-page-helpers': todoRule(
        'Comment the CMS helpers that Shopware 6.8 removes',
        members(['sw-category-detail', 'sw-product-detail'], ['extractSlotOverrides', 'getCmsPageOverrides', 'deleteSpecifcKeys'], REMOVED),
        members(['sw-cms-detail', 'sw-cms-create'], ['resetCmsPageState'], 'It is removed in 6.8. Use resetRelatedStores().'),
        members(['sw-cms-inherit-wrapper'], ['isSystemDefaultLanguage'], 'It is removed in 6.8. Use Shopware.Store.get(\'context\').isSystemDefaultLanguage.'),
        members(['sw-cms-section'], ['pageSlotconfigError'], REMOVED),
        members(['sw-cms-el-product-slider'], ['navArrowsClasses'], REMOVED),
        members(['sw-cms-list'], ['associatedCategoryBuckets', 'associatedProductBuckets'], REMOVED),
        members(['sw-cms-layout-modal'], ['gridPreSelection', 'selectInGrid'], REMOVED),
    ),

    'no-removed-settings-product-promotion-members': todoRule(
        'Comment the settings, product and promotion members that Shopware 6.8 removes',
        ...SETTINGS_PRODUCT_PROMOTION_MEMBERS.map(([components, names, hint]) => members(components, names, hint)),
        removedComponents(['sw-settings-services-dashboard-banner', 'sw-promotion-v2-empty-state-hero'], REMOVED),
    ),

    'no-changed-default-behaviour': todoRule(
        'Comment code that relies on Administration defaults Shopware 6.8 changes',
        members(['sw-media-library'], ['sorting'], 'The media library sorts by createdAt desc by default in 6.8. Set sorting if you rely on another order.'),
        members(
            ['sw-media-preview-v2'],
            ['buildSourceSet'],
            'Media and thumbnail URLs come percent-encoded from the server in 6.8. Remove your own encodeURI() to avoid double encoding.',
        ),
        {
            script: [
                {
                    type: 'member',
                    service: 'mailService',
                    properties: ['sendMailTemplate'],
                    filter: sendsWithoutTemplateData,
                    hint: 'sendMailTemplate() no longer fills templateData from the mail template type in 6.8. Pass templateData explicitly.',
                },
                {
                    type: 'member',
                    object: '*.mailTemplateType',
                    properties: ['templateData'],
                    hint: 'The stored templateData of mail template types is removed in 6.8. Build the template data yourself.',
                },
            ],
        },
    ),
};
