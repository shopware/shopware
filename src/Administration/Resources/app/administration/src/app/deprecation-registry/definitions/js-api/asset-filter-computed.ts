/**
 * @sw-package framework
 */

import { componentMember, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.asset-filter-computed',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'These components no longer have the assetFilter computed. Templates need their own computed that returns the filter.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-the-assetfilter-computed-of-the-empty-state-components')],
    usage: [
        componentMember({
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
                'sw-settings-listing-option-criteria-grid',
                'sw-settings-listing',
                'sw-settings-product-feature-sets-values-card',
                'sw-tax-rule-card',
            ],
            member: 'assetFilter',
            to: "Shopware.Filter.getByName('asset')",
            replaceInScript: "Shopware.Filter.getByName('asset')",
            fix: 'auto',
        }),
    ],
});
