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
        'additionalText',
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
        'manufacturerNumber',
        'unitId',
        'taxId',
        'coverId',
        'productMediaVersionId',
        'propertyIds',
        'optionIds',
        'orders',
        'customers',
        'seoUrls',
        'translated',
        'tagIds',
        'customerGroupId',
        'newsletterRecipients',
        'numberRanges',
        'promotionSalesChannels',
        'seoUrlTemplates',
        'shippingMethods',
        'markAsTopseller',
        'variantRestrictions',
        'configuratorGroupConfig',
        'cmsPageId',
        'navigationCategoryId',
        'navigationCategoryVersionId',
        'footerCategoryId',
        'footerCategoryVersionId',
        'serviceCategoryId',
        'serviceCategoryVersionId',
        'position',
        'navigationCategory',
        'footerCategory',
        'serviceCategory',
        'numberRangeSalesChannels',
        'documentBaseConfigSalesChannels'
    ];

    const entityBlacklist = {
        price: [
            'linked'
        ],
        tax: [
            'customFields',
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
            'customFields',
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
            'productBoxLayout',
            'navigationSalesChannels',
            'footerSalesChannels',
            'serviceSalesChannels',
            'cmsPage',
            'externalLink',
            'slotConfig'
        ],
        product_manufacturer: [
            'link',
            'customFields',
            'media',
            'description'
        ],
        unit: [
            'customFields',
            'shortCode'
        ],
        product_configurator_setting: [
            'versionId',
            'prices',
            'createdAt',
            'updatedAt',
            'customFields'
        ],
        property_group_option: [
            'colorHexCode',
            'productConfigurators',
            'productServices',
            'productProperties',
            'productOptions',
            'customFields'
        ],
        property_group: [
            'description',
            'filterable',
            'comparable',
            'displayType',
            'sortingType',
            'options',
            'customFields'
        ],
        product_visibility: [
            'id'
        ],
        sales_channel: [
            'name',
            'accessKey',
            'configuration',
            'customFields',
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
            'language',
            'paymentMethod',
            'shippingMethod',
            'currency',
            'customerGroup',
            'shortName'
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
            'prices',
            'services',
            'properties',
            'searchKeywords',
            'categoriesRo',
            'canonicalUrl',
            'purchaseSteps',
            'options'
        ]
    };

    const productCustomFields = {};

    return {
        isPropertyInBlacklist,
        addToGeneralBlacklist,
        addToEntityBlacklist,
        productCustomFields
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
