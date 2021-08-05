/**
 * @module app/service/search-type
 */

/**
 *
 * @memberOf module:core/service/search-type
 * @constructor
 * @method createSearchTypeService
 * @returns {Object}
 */
export default function createSearchTypeService() {
    const $typeStore = {
        product: {
            entityName: 'product',
            placeholderSnippet: 'sw-product.general.placeholderSearchBar',
            listingRoute: 'sw.product.index',
        },
        category: {
            entityName: 'category',
            placeholderSnippet: 'sw-category.general.placeholderSearchBar',
            listingRoute: 'sw.category.index',
        },
        landing_page: {
            entityName: 'landing_page',
            placeholderSnippet: 'sw-landing-page.general.placeholderSearchBar',
            listingRoute: 'sw.category.index',
        },
        customer: {
            entityName: 'customer',
            placeholderSnippet: 'sw-customer.general.placeholderSearchBar',
            listingRoute: 'sw.customer.index',
        },
        order: {
            entityName: 'order',
            placeholderSnippet: 'sw-order.general.placeholderSearchBar',
            listingRoute: 'sw.order.index',
        },
        media: {
            entityName: 'media',
            placeholderSnippet: 'sw-media.general.placeholderSearchBar',
            listingRoute: 'sw.media.index',
        },
    };

    return {
        getTypeByName,
        upsertType,
        getTypes,
        removeType,
    };

    function getTypeByName(type) {
        return $typeStore[type];
    }

    function upsertType(name, configuration) {
        $typeStore[name] = { ...$typeStore[name], ...configuration };
    }

    function getTypes() {
        return $typeStore;
    }

    function removeType(name) {
        delete $typeStore[name];
    }
}
