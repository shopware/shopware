/**
 * @sw-package framework
 */

import { componentMembers, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.custom-field-set-loaders',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The custom field set loaders of these components are removed.',
    references: [upgradeGuide('UPGRADE-6.8.md#custom-field-set-loader-computed-properties-removed')],
    usage: [
        ...componentMembers({
            components: [
                'sw-category-detail',
                'sw-customer-detail-base',
                'sw-manufacturer-detail',
                'sw-order-detail-details',
                'sw-settings-units-detail',
                'sw-bulk-edit-customer',
                'sw-bulk-edit-order',
                'sw-bulk-edit-product',
            ],
            members: ['customFieldSetRepository', 'customFieldSetCriteria'],
            to: "Shopware.Service('customFieldDataProviderService').getCustomFieldSets(entityName)",
        }),
        ...componentMembers({
            components: ['sw-category-detail'],
            members: ['customFieldSetLandingPageCriteria'],
            to: "Shopware.Service('customFieldDataProviderService').getCustomFieldSets(entityName)",
        }),
        ...componentMembers({
            components: ['sw-customer-detail-addresses'],
            members: ['customFieldSetRepository'],
            to: "Shopware.Service('customFieldDataProviderService').getCustomFieldSets(entityName)",
        }),
        ...componentMembers({
            components: ['sw-sales-channel-detail'],
            members: ['customFieldRepository'],
            to: "Shopware.Service('customFieldDataProviderService').getCustomFieldSets(entityName)",
        }),
    ],
});
