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
            entityService: 'productService',
            placeholderSnippet: 'sw-product.general.placeholderSearchBar',
            listingRoute: 'sw.product.index'
        },
        category: {
            entityName: 'category',
            entityService: 'categoryService',
            placeholderSnippet: 'sw-category.general.placeholderSearchBar',
            listingRoute: 'sw.category.index'
        },
        customer: {
            entityName: 'customer',
            entityService: 'customerService',
            placeholderSnippet: 'sw-customer.general.placeholderSearchBar',
            listingRoute: 'sw.customer.index'
        },
        order: {
            entityName: 'order',
            entityService: 'orderService',
            placeholderSnippet: 'sw-order.general.placeholderSearchBar',
            listingRoute: 'sw.order.index'
        },
        media: {
            entityName: 'media',
            entityService: 'mediaService',
            placeholderSnippet: 'sw-media.general.placeholderSearchBar',
            listingRoute: 'sw.media.index'
        }
    };

    return {
        getTypeByName,
        upsertType,
        getTypes,
        removeType
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
