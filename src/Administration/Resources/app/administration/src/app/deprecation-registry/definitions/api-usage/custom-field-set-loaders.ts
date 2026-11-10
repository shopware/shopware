/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, upgradeGuide } from '../helpers';

const MESSAGE =
    "The custom field set loaders are removed in 6.8. Load the sets with Shopware.Service('customFieldDataProviderService').getCustomFieldSets(entityName).";

export default apiUsageMigration({
    id: 'api.custom-field-set-loaders',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'These components switched to a shared custom field set loader and lost their own loader members.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#custom-field-set-loader-computed-properties-removed'),
    ],
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
            names: [
                'customFieldSetRepository',
                'customFieldSetCriteria',
            ],
            message: MESSAGE,
        }),
        componentMember({
            components: ['sw-category-detail'],
            names: ['customFieldSetLandingPageCriteria'],
            message: MESSAGE,
        }),
        componentMember({
            components: ['sw-customer-detail-addresses'],
            names: ['customFieldSetRepository'],
            message: MESSAGE,
        }),
        componentMember({ components: ['sw-sales-channel-detail'], names: ['customFieldRepository'], message: MESSAGE }),
    ],
});
