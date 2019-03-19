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
            iconName: 'default-symbol-products',
            iconColor: '#57D9A3',
            entityService: 'productService',
            placeholderSnippet: 'sw-product.general.placeholderSearchBar',
            listingRoute: 'sw.product.index'
        },
        customer: {
            entityName: 'customer',
            iconName: 'default-avatar-multiple',
            iconColor: '#F88962',
            entityService: 'customerService',
            placeholderSnippet: 'sw-customer.general.placeholderSearchBar',
            listingRoute: 'sw.customer.index'
        },
        category: {
            entityName: 'category',
            iconName: 'default-package-closed',
            iconColor: '#57D9A3',
            entityService: 'categoryService',
            placeholderSnippet: 'sw-category.general.placeholderSearchBar',
            listingRoute: 'sw.category.index'
        },
        order: {
            entityName: 'order',
            iconName: 'default-shopping-paper-bag',
            iconColor: '#A092F0',
            entityService: 'orderService',
            placeholderSnippet: 'sw-order.general.placeholderSearchBar',
            listingRoute: 'sw.order.index'
        },
        media: {
            entityName: 'media',
            iconName: 'default-object-image',
            iconColor: '#FFD700',
            entityService: 'mediaService',
            placeholderSnippet: 'sw-media.general.placeholderSearchBar',
            listingRoute: 'sw.media.index'
        },
        cms_page: {
            entityName: 'cms_page',
            iconName: 'default-object-marketing',
            iconColor: '#ff68b4',
            entityService: 'cmsService',
            placeholderSnippet: 'sw-cms.general.placeholderSearchBar',
            listingRoute: 'sw.cms.index'
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
        $typeStore[name] = { ...$typeStore[name], ...{ configuration } };
    }

    function getTypes() {
        return $typeStore;
    }

    function removeType(name) {
        delete $typeStore[name];
    }
}
