/**
 * @sw-package framework
 */

import { apiUsageMigration, replaceComponentMember, upgradeGuide } from '../helpers';

const DATE_FILTER_COMPONENTS = [
    'sw-advanced-selection-rule',
    'sw-media-folder-item',
    'sw-media-media-item',
    'sw-settings-logging-list',
    'sw-extension-card-base',
    'sw-self-maintained-extension-card',
    'sw-import-export-activity',
    'sw-import-export-activity-result-modal',
    'sw-import-export-activity-log-info-modal',
    'sw-cms-list',
    'sw-cms-layout-modal',
    'sw-review-detail',
    'sw-review-list',
    'sw-promotion-v2-individual-codes-behavior',
    'sw-settings-rule-list',
    'sw-product-list',
    'sw-product-detail-reviews',
    'sw-settings-snippet-set-list',
    'sw-product-stream-list',
    'sw-order-list',
    'sw-order-send-document-modal',
    'sw-order-state-history-card-entry',
    'sw-order-document-card',
    'sw-order-general-info',
    'sw-dashboard-statistics',
    'sw-sales-channel-list',
    'sw-sales-channel-detail-base',
    'sw-sales-channel-create-base',
    'sw-newsletter-recipient-list',
];

const ASSET_FILTER_COMPONENTS = [
    'sw-cms-layout-assignment-modal',
    'sw-flow-list',
    'sw-mail-header-footer-list',
    'sw-mail-template-list',
    'sw-order-customer-grid',
    'sw-product-detail-context-prices',
    'sw-product-detail-cross-selling',
    'sw-promotion-v2-individual-codes-behavior',
    'sw-sales-channel-products-assignment-dynamic-product-groups',
    'sw-settings-listing-option-criteria-grid',
    'sw-settings-listing',
    'sw-settings-product-feature-sets-values-card',
    'sw-tax-rule-card',
];

export default [
    apiUsageMigration({
        id: 'api.date-filter-computed',
        deprecatedIn: '6.7.0',
        removedIn: '6.8.0',
        description: 'The unused dateFilter computed was removed from these components. Resolve the filter directly.',
        references: [
            upgradeGuide('UPGRADE-6.8.md#removed-unused-datefilter-computeds'),
        ],
        usage: [
            replaceComponentMember({
                components: DATE_FILTER_COMPONENTS,
                name: 'dateFilter',
                with: "Shopware.Filter.getByName('date')",
                message: "The dateFilter computed is removed in 6.8. Use Shopware.Filter.getByName('date').",
                templateMessage:
                    "The dateFilter computed is removed in 6.8. Add a dateFilter computed that returns Shopware.Filter.getByName('date') to your override, or use <sw-time-ago>.",
            }),
        ],
    }),
    apiUsageMigration({
        id: 'api.asset-filter-computed',
        deprecatedIn: '6.7.0',
        removedIn: '6.8.0',
        description:
            'The assetFilter computed became unused when the empty states moved to mt-empty-state and was removed. Resolve the filter directly.',
        references: [
            upgradeGuide('UPGRADE-6.8.md#removed-the-assetfilter-computed-of-the-empty-state-components'),
        ],
        usage: [
            replaceComponentMember({
                components: ASSET_FILTER_COMPONENTS,
                name: 'assetFilter',
                with: "Shopware.Filter.getByName('asset')",
                message: "The assetFilter computed is removed in 6.8. Use Shopware.Filter.getByName('asset').",
                templateMessage:
                    "The assetFilter computed is removed in 6.8. Add an assetFilter computed that returns Shopware.Filter.getByName('asset') to your override.",
            }),
        ],
    }),
];
