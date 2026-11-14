/**
 * @sw-package framework
 *
 * The filter computeds that 6.8 removes, from the `@deprecated tag:v6.8.0` markers. The `assetFilter` computeds of
 * `sw-extension-my-extensions-listing` and `sw-extension-store-landing-page` are tagged for 6.9 and stay out.
 */

module.exports = {
    dateFilter: {
        filter: 'date',
        components: [
            'sw-advanced-selection-rule',
            'sw-cms-layout-modal',
            'sw-cms-list',
            'sw-dashboard-statistics',
            'sw-extension-card-base',
            'sw-import-export-activity',
            'sw-import-export-activity-log-info-modal',
            'sw-import-export-activity-result-modal',
            'sw-media-folder-item',
            'sw-media-media-item',
            'sw-newsletter-recipient-list',
            'sw-order-document-card',
            'sw-order-general-info',
            'sw-order-list',
            'sw-order-send-document-modal',
            'sw-order-state-history-card-entry',
            'sw-product-detail-reviews',
            'sw-product-list',
            'sw-product-stream-list',
            'sw-promotion-v2-individual-codes-behavior',
            'sw-review-detail',
            'sw-review-list',
            'sw-sales-channel-detail-base',
            'sw-sales-channel-list',
            'sw-settings-logging-list',
            'sw-settings-rule-list',
            'sw-settings-snippet-set-list',
        ],
    },
    assetFilter: {
        filter: 'asset',
        components: [
            'sw-cms-layout-assignment-modal',
            'sw-flow-list',
            'sw-mail-header-footer-list',
            'sw-mail-template-list',
            'sw-order-customer-grid',
            'sw-product-detail-context-prices',
            'sw-product-detail-cross-selling',
            'sw-promotion-v2-individual-codes-behavior',
            'sw-sales-channel-products-assignment-dynamic-product-groups',
            'sw-settings-listing',
            'sw-settings-listing-option-criteria-grid',
            'sw-settings-product-feature-sets-values-card',
            'sw-tax-rule-card',
        ],
    },
};
