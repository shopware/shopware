/**
 * @sw-package framework
 */

import { componentMember, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.date-filter-computed',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'These components no longer have the dateFilter computed. Templates need their own computed that returns the filter.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-unused-datefilter-computeds')],
    usage: [
        componentMember({
            components: [
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
            ],
            member: 'dateFilter',
            to: "Shopware.Filter.getByName('date')",
            replaceInScript: "Shopware.Filter.getByName('date')",
            fix: 'auto',
        }),
    ],
});
