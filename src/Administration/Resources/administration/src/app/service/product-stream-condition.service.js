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
    const blacklist = [
        'createdAt',
        'updatedAt',
        'afterCategoryId',
        'versionId',
        'afterCategoryVersionId',
        'autoIncrement',
        'canonicalUrl',
        'children',
        'facetIds',
        'mediaId',
        'parent',
        'parentId',
        'parentVersionId',
        'sortingIds',
        'metaTitle',
        'metaDescription',
        'metaKeywords',
        'products',
        'product',
        'productId',
        'productVersionId',
        'optionId',
        'groupId',
        'media',
        'salesChannelId',
        'typeId',
        'languageId',
        'currencyId',
        'paymentMethodId',
        'shippingMethodId',
        'countryId',
        'navigationId',
        'navigationVersionId',
        'mailHeaderFooterId',
        'manufacturerId',
        'unitId',
        'taxId',
        'coverId',
        'productMediaVersionId',
        'datasheetIds',
        'variationIds',
        'orders',
        'customers'
    ];

    const entityBlacklist = {
        price: [
            'linked'
        ],
        tax: [
            'attributes',
            'name',
            'products',
            'productServices'
        ],
        tag: [
            'categories'
        ],
        category: [
            'displayNestedProducts',
            'path',
            'level',
            'template',
            'attributes',
            'childCount',
            'cmsDescription',
            'cmsHeadline',
            'createdAt',
            'extensions',
            'external',
            'hideFilter',
            'hideSortings',
            'hideTop',
            'media',
            'navigations',
            'nestedProducts',
            'productBoxLayout'
        ],
        product_manufacturer: [
            'link',
            'attributes',
            'media'
        ],
        unit: [
            'attributes',
            'shortCode'
        ],
        product_configurator: [
            'versionId',
            'prices',
            'createdAt',
            'updatedAt',
            'attributes'
        ],
        configuration_group_option: [
            'position',
            'colorHexCode',
            'productConfigurators',
            'productServices',
            'productDatasheets',
            'productVariations',
            'attributes'
        ],
        configuration_group: [
            'description',
            'position',
            'filterable',
            'comparable',
            'displayType',
            'sortingType',
            'options',
            'attributes'
        ],
        product_visibility: [
            'id'
        ],
        sales_channel: [
            'name',
            'accessKey',
            'configuration',
            'attributes',
            'extensions',
            'type',
            'currencies',
            'languages',
            'countries',
            'paymentMethods',
            'shippingMethods',
            'country',
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
            'blacklistIds',
            'whitelistIds',
            'productManufacturerVersionId',
            'listingPrices',
            'categoryTree',
            'extensions',
            'productServices',
            'cover',
            'metaTitle',
            'priceRules',
            'services',
            'datasheet',
            'searchKeywords',
            'categoriesRo',
            'canonicalUrl',
            'position',
            'purchaseSteps'
        ]
    };

    return {
        isPropertyInBlacklist,
        addToGeneralBlacklist,
        addToEntityBlacklist
    };

    function isPropertyInBlacklist(definition, property) {
        return blacklist.includes(property)
            || (entityBlacklist[definition] && entityBlacklist[definition].includes(property));
    }

    function addToGeneralBlacklist(properties) {
        blacklist.push(...properties);
    }

    function addToEntityBlacklist(entity, properties) {
        if (entityBlacklist[entity]) {
            entityBlacklist[entity].push(...properties);
            return;
        }

        entityBlacklist[entity] = properties;
    }
}
