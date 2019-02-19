/**
 * @module app/service/product-stream-condition
 */

/**
 * @memberOf module:app/service/product-stream-condition
 * @constructor
 * @method conditionService
 * @returns {Object}
 */
export default function conditionService() {
    const blacklist = {
        price: [
            'linked'
        ],
        tax: [
            'attributes',
            'name',
            'createdAt',
            'updatedAt',
            'products',
            'productServices'
        ],
        category: [
            'afterCategoryId',
            'versionId',
            'afterCategoryVersionId',
            'displayNestedProducts',
            'autoIncrement',
            'path',
            'level',
            'template',
            'attributes',
            'canonicalUrl',
            'childCount',
            'children',
            'cmsDescription',
            'cmsHeadline',
            'createdAt',
            'extensions',
            'external',
            'facetIds',
            'hideFilter',
            'hideSortings',
            'hideTop',
            'media',
            'mediaId',
            'metaDescription',
            'metaKeywords',
            'metaTitle',
            'navigations',
            'nestedProducts',
            'parent',
            'parentId',
            'parentVersionId',
            'productBoxLayout',
            'products',
            'sortingIds',
            'updatedAt'
        ],
        product_manufacturer: [
            'versionId',
            'mediaId',
            'link',
            'createdAt',
            'updatedAt',
            'metaTitle',
            'metaDescription',
            'metaKeywords',
            'attributes',
            'media',
            'products'
        ],
        unit: [
            'attributes',
            'createdAt',
            'updatedAt',
            'products',
            'shortCode'
        ],
        product_configurator: [
            'versionId',
            'productId',
            'productVersionId',
            'optionId',
            'prices',
            'createdAt',
            'updatedAt',
            'attributes',
            'product'
        ],
        configuration_group_option: [
            'groupId',
            'position',
            'colorHexCode',
            'mediaId',
            'media',
            'productConfigurators',
            'productServices',
            'productDatasheets',
            'productVariations',
            'attributes',
            'createdAt',
            'updatedAt'
        ],
        configuration_group: [
            'description',
            'position',
            'filterable',
            'comparable',
            'displayType',
            'sortingType',
            'options',
            'attributes',
            'createdAt',
            'updatedAt'
        ],
        product_visibility: [
            'id',
            'productId',
            'productVersionId',
            'salesChannelId',
            'createdAt',
            'updatedAt',
            'product'
        ],
        sales_channel: [
            'typeId',
            'languageId',
            'currencyId',
            'paymentMethodId',
            'shippingMethodId',
            'countryId',
            'navigationId',
            'navigationVersionId',
            'mailHeaderFooterId',
            'name',
            'accessKey',
            'configuration',
            'attributes',
            'createdAt',
            'updatedAt',
            'extensions',
            'type',
            'currencies',
            'languages',
            'countries',
            'paymentMethods',
            'shippingMethods',
            'country',
            'orders',
            'customers',
            'domains',
            'systemConfigs',
            'navigation',
            'productVisibilities',
            'mailHeaderFooter',
            'mailTemplates',
            'seoUrls',
            'language',
            'taxCalculationType',
            'paymentMethod',
            'shippingMethod',
            'currency'
        ],
        product: [
            'versionId',
            'parentId',
            'parentVersionId',
            'blacklistIds',
            'whitelistIds',
            'autoIncrement',
            'manufacturerId',
            'productManufacturerVersionId',
            'unitId',
            'taxId',
            'coverId',
            'productMediaVersionId',
            'listingPrices',
            'categoryTree',
            'datasheetIds',
            'variationIds',
            'extensions',
            'parent',
            'children',
            'products',
            'productServices',
            'mediaId',
            'media',
            'cover',
            'metaTitle',
            'priceRules',
            'services',
            'datasheet',
            'searchKeywords',
            'categoriesRo',
            'canonicalUrl',
            'position'
        ]
    };

    return {
        blacklist
    };
}
