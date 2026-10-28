/**
 * @sw-package checkout
 */

import type RepositoryType from 'src/core/data/repository.data';

const { Criteria } = Shopware.Data;

/**
 * The deprecated `computed` members of `sw-bulk-edit-order`, apart from the component so the major removes them
 * with this file.
 *
 * @private
 * @example
 * computed: { ...deprecatedComputed, selectedIds() { … } }
 */
export const deprecatedComputed = {
    // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
    customFieldSetRepository(this: {
        repositoryFactory: { create(entity: 'custom_field_set'): RepositoryType<'custom_field_set'> };
    }) {
        Shopware.Feature.triggerDeprecationOrThrow(
            'V6_8_0_0',
            'sw-bulk-edit-order.customFieldSetRepository is deprecated. Use customFieldDataProviderService instead.',
        );

        return this.repositoryFactory.create('custom_field_set');
    },

    // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
    customFieldSetCriteria() {
        Shopware.Feature.triggerDeprecationOrThrow(
            'V6_8_0_0',
            'sw-bulk-edit-order.customFieldSetCriteria is deprecated. Use customFieldDataProviderService instead.',
        );

        const criteria = new Criteria(1, null);

        criteria.addFilter(Criteria.equals('relations.entityName', 'order'));

        return criteria;
    },
};
