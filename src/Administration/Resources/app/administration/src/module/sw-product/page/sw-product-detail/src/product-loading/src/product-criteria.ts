import { Criteria } from '../dependencies';

/**
 * @sw-package inventory
 */

/**
 * The criteria for one product with every association the detail tabs show, each list in the order the tabs show it.
 * Load a variant's parent with the same criteria, so inherited data looks like the variant's own.
 *
 * @example
 * productRepository.get(productId, context, createProductCriteria());
 *
 * @private
 */
export function createProductCriteria(): Criteria {
    const criteria = new Criteria(1, 1);
    criteria.setTotalCountMode(0);

    criteria.getAssociation('media').addSorting(Criteria.sort('position', 'ASC'));
    criteria.addAssociation('media.media');

    criteria.getAssociation('prices').addSorting(Criteria.sort('quantityStart', 'ASC', true));

    criteria.getAssociation('tags').addSorting(Criteria.sort('name', 'ASC'));

    criteria.getAssociation('seoUrls').addFilter(Criteria.equals('isCanonical', true));

    criteria
        .getAssociation('crossSellings')
        .addSorting(Criteria.sort('position', 'ASC'))
        .getAssociation('assignedProducts')
        .addSorting(Criteria.sort('position', 'ASC'))
        .addAssociation('product')
        .getAssociation('product')
        .addAssociation('options.group');

    criteria
        .addAssociation('cover.media')
        .addAssociation('openGraphMedia')
        .addAssociation('categories')
        .addAssociation('visibilities.salesChannel')
        .addAssociation('options')
        .addAssociation('configuratorSettings.option')
        .addAssociation('unit')
        .addAssociation('productReviews')
        .addAssociation('seoUrls')
        .addAssociation('mainCategories')
        .addAssociation('options.group')
        .addAssociation('customFieldSets')
        .addAssociation('featureSet')
        .addAssociation('cmsPage')
        .addAssociation('translations')
        .addAssociation('downloads.media');

    criteria.getAssociation('manufacturer').addAssociation('media');

    return criteria;
}

/**
 * The criteria for the property options of a product, sorted by name.
 * Pass `assignedToProductId` to keep only the options assigned to that product itself, not those a variant inherits.
 *
 * @example
 * propertyRepository.search(createPropertyCriteria(product.propertyIds, product.id));
 *
 * @private
 */
export function createPropertyCriteria(propertyIds: string[], assignedToProductId?: string): Criteria {
    const criteria = new Criteria(1, null);
    criteria.addSorting(Criteria.sort('name', 'ASC', true));
    criteria.setIds(propertyIds);

    if (assignedToProductId !== undefined) {
        criteria.addFilter(Criteria.equals('productProperties.id', assignedToProductId));
    }

    return criteria;
}

/**
 * The criteria for the feature set a new product starts with: the oldest one named "Default" or "Standard".
 *
 * @example
 * featureSetRepository.search(createDefaultFeatureSetCriteria());
 *
 * @private
 */
export function createDefaultFeatureSetCriteria(): Criteria {
    const criteria = new Criteria(1, 1);

    criteria.addSorting(Criteria.sort('createdAt', 'ASC')).addFilter(Criteria.equalsAny('name', ['Default', 'Standard']));

    return criteria;
}
