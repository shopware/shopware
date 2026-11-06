/**
 * @sw-package framework
 */

import { componentMember, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.custom-field-set-loaders',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The custom field set loaders of these components are removed.',
    references: [upgradeGuide('UPGRADE-6.8.md#custom-field-set-loader-computed-properties-removed')],
    usage: [
        componentMember({
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
            member: 'customFieldSetRepository',
            to: "Shopware.Service('customFieldDataProviderService').getCustomFieldSets(entityName)",
        }),
        componentMember({
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
            member: 'customFieldSetCriteria',
            to: "Shopware.Service('customFieldDataProviderService').getCustomFieldSets(entityName)",
        }),
        componentMember({
            components: ['sw-category-detail'],
            member: 'customFieldSetLandingPageCriteria',
            to: "Shopware.Service('customFieldDataProviderService').getCustomFieldSets(entityName)",
        }),
        componentMember({
            components: ['sw-customer-detail-addresses'],
            member: 'customFieldSetRepository',
            to: "Shopware.Service('customFieldDataProviderService').getCustomFieldSets(entityName)",
        }),
        componentMember({
            components: ['sw-sales-channel-detail'],
            member: 'customFieldRepository',
            to: "Shopware.Service('customFieldDataProviderService').getCustomFieldSets(entityName)",
        }),
    ],
});
