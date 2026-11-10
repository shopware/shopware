/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, removedComponent, upgradeGuide } from '../helpers';

const REMOVED = 'It is removed in 6.8 without replacement.';
const RULE_FILTER =
    'It is removed in 6.8. Remove the :rule-filter binding, sw-select-rule-create filters the rules by default.';

/** Each entry: the components, their removed members and the hint for the review comment. */
const MEMBERS: Array<[string[], string[], string]> = [
    [
        ['sw-settings-index'],
        ['hideSettingRenameBanner', 'getUserConfig', 'onCloseSettingRenameBanner'],
        `The settings rename banner is removed in 6.8. ${REMOVED}`,
    ],
    [
        ['sw-settings-customer-group-detail'],
        [
            'seoUrlRepository',
            'seoUrlCriteria',
            'loadSeoUrls',
            'getSeoUrl',
        ],
        `The SEO URLs are built in the template in 6.8. ${REMOVED}`,
    ],
    [
        ['sw-settings-number-range-detail'],
        ['selectedNumberRangeSalesChannels'],
        'It is removed in 6.8. Use selectedSalesChannelsCollection.',
    ],
    [
        ['sw-settings-number-range-detail'],
        ['loadSalesChannels'],
        'It is removed in 6.8. Use buildSelectedSalesChannelsCollection().',
    ],
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
    [
        ['sw-product-cross-selling-form'],
        ['productCrossSellingRepository', 'crossSellingAssigmentRepository', 'disablePositioning'],
        REMOVED,
    ],
    [['sw-product-clone-modal', 'sw-product-modal-variant-generation'], ['progressInPercentage'], REMOVED],
    [['sw-product-packaging-form'], ['isLoading'], REMOVED],
    [['sw-product-list'], ['productTypeOptions'], REMOVED],
    [['sw-promotion-v2-cart-condition-form', 'sw-promotion-discount-component'], ['ruleFilter'], RULE_FILTER],
    [['sw-promotion-v2-settings-rule-selection', 'sw-promotion-v2-settings-trigger'], ['ruleCriteria'], RULE_FILTER],
    [
        ['sw-promotion-v2-conditions'],
        ['personaRuleFilter', 'cartConditionsRuleFilter', 'orderConditionsFilter'],
        RULE_FILTER,
    ],
    [['sw-promotion-discount-component'], ['maxValueAdvancedPricesTooltip'], REMOVED],
    [['sw-promotion-detail-discounts'], ['deleteDiscountId', 'repository'], REMOVED],
];

export default apiUsageMigration({
    id: 'api.settings-product-promotion-members',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Members of settings, product and promotion components were removed.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-settings-product-and-promotion-members'),
    ],
    usage: [
        ...MEMBERS.map(([components, names, message]) => componentMember({ components, names, message })),
        removedComponent({
            names: [
                'sw-settings-services-dashboard-banner',
                'sw-promotion-v2-empty-state-hero',
            ],
            message: `This component is removed in 6.8. ${REMOVED}`,
        }),
    ],
});
