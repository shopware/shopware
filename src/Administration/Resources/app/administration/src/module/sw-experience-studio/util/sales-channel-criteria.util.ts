import type CriteriaType from 'src/core/data/criteria.data';

const { Criteria } = Shopware.Data;

const STOREFRONT_SALES_CHANNEL_TYPE_ID = '8a243080f92e4c719546314b577cf82b';

/**
 * @private
 * @sw-package discovery
 */
export function getStorefrontSalesChannelCriteria(limit = 25): CriteriaType {
    return new Criteria(1, limit)
        .addFilter(Criteria.equals('typeId', STOREFRONT_SALES_CHANNEL_TYPE_ID))
        .addSorting(Criteria.sort('name', 'ASC'));
}
