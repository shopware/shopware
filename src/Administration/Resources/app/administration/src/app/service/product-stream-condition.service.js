const utils = Shopware.Utils;

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
        'childCount',
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
        'documentBaseConfigSalesChannels',
        'translations',
        'translation',
        'mainCategories'
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
            'shortName',
            'themes'
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

    const productFilterTypes = {
        equals: {
            identifier: 'equals',
            label: 'sw-product-stream.filter.type.equals'
        },

        equalsAny: {
            identifier: 'equalsAny',
            label: 'sw-product-stream.filter.type.equalsAny'
        },

        contains: {
            identifier: 'contains',
            label: 'sw-product-stream.filter.type.contains'
        },

        lessThan: {
            identifier: 'lessThan',
            label: 'sw-product-stream.filter.type.lessThan'
        },

        greaterThan: {
            identifier: 'greaterThan',
            label: 'sw-product-stream.filter.type.greaterThan'
        },

        lessThanEquals: {
            identifier: 'lessThanEquals',
            label: 'sw-product-stream.filter.type.lessThanEquals'
        },

        greaterThanEquals: {
            identifier: 'greaterThanEquals',
            label: 'sw-product-stream.filter.type.greaterThanEquals'
        },

        notEquals: {
            identifier: 'notEquals',
            label: 'sw-product-stream.filter.type.notEquals'
        },

        notEqualsAny: {
            identifier: 'notEqualsAny',
            label: 'sw-product-stream.filter.type.notEqualsAny'
        },

        notContains: {
            identifier: 'notContains',
            label: 'sw-product-stream.filter.type.notContains'
        },

        range: {
            identifier: 'range',
            label: 'sw-product-stream.filter.type.range'
        },

        not: {
            identifier: 'not',
            label: 'sw-product-stream.filter.type.not'
        }
    };

    const operatorSets = {
        boolean: [
            productFilterTypes.equals
        ],
        string: [
            productFilterTypes.equals,
            productFilterTypes.notEquals,
            productFilterTypes.equalsAny,
            productFilterTypes.notEqualsAny,
            productFilterTypes.contains,
            productFilterTypes.notContains
        ],

        date: [
            productFilterTypes.equals,
            productFilterTypes.greaterThan,
            productFilterTypes.greaterThanEquals,
            productFilterTypes.lessThan,
            productFilterTypes.lessThanEquals,
            productFilterTypes.notEquals,
            productFilterTypes.range
        ],

        uuid: [
            productFilterTypes.equals,
            productFilterTypes.notEquals,
            productFilterTypes.equalsAny,
            productFilterTypes.notEqualsAny
        ],

        int: [
            productFilterTypes.equals,
            productFilterTypes.greaterThan,
            productFilterTypes.greaterThanEquals,
            productFilterTypes.lessThan,
            productFilterTypes.lessThanEquals,
            productFilterTypes.notEquals,
            productFilterTypes.range
        ],

        float: [
            productFilterTypes.equals,
            productFilterTypes.greaterThan,
            productFilterTypes.greaterThanEquals,
            productFilterTypes.lessThan,
            productFilterTypes.lessThanEquals,
            productFilterTypes.notEquals,
            productFilterTypes.range
        ],

        object: [
            productFilterTypes.equals,
            productFilterTypes.greaterThan,
            productFilterTypes.greaterThanEquals,
            productFilterTypes.lessThan,
            productFilterTypes.lessThanEquals,
            productFilterTypes.notEquals,
            productFilterTypes.range
        ],

        default: [
            productFilterTypes.equals,
            productFilterTypes.notEquals,
            productFilterTypes.equalsAny,
            productFilterTypes.notEqualsAny
        ]
    };

    return {
        isPropertyInBlacklist,
        addToGeneralBlacklist,
        addToEntityBlacklist,
        getConditions,
        getAndContainerData,
        isAndContainer,
        getOrContainerData,
        isOrContainer,
        getPlaceholderData,
        getComponentByCondition,
        getOperatorSet,
        negateOperator,
        getOperator,
        isNegatedType,
        isRangeType
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

    function getConditions() {
        return [
            {
                type: 'productStreamFilter',
                component: 'sw-product-stream-filter',
                label: 'product',
                scopes: ['product']
            }
        ];
    }

    function getAndContainerData() {
        return { type: 'multi', field: null, parameters: null, operator: 'AND' };
    }

    function isAndContainer(condition) {
        return condition.type === 'multi' && condition.operator === 'AND';
    }

    function getOrContainerData() {
        return { type: 'multi', field: null, parameters: null, operator: 'OR' };
    }

    function isOrContainer(condition) {
        return condition.type === 'multi' && condition.operator === 'OR';
    }

    function getPlaceholderData() {
        return { type: 'equals', field: 'id', parameters: null, operator: null };
    }

    function getComponentByCondition(condition) {
        if (isAndContainer(condition)) {
            return 'sw-condition-and-container';
        }

        if (isOrContainer(condition)) {
            return 'sw-condition-or-container';
        }

        return 'sw-product-stream-filter';
    }

    function getOperatorSet(type) {
        if (!utils.types.isString(type) || type === '') {
            return operatorSets.default;
        }

        return operatorSets[type] || operatorSets.default;
    }

    function getOperator(type) {
        return productFilterTypes[type];
    }

    function negateOperator(type) {
        switch (type) {
            case 'equals':
                return productFilterTypes.notEquals;
            case 'notEquals':
                return productFilterTypes.equals;
            case 'equalsAny':
                return productFilterTypes.notEqualsAny;
            case 'notEqualsAny':
                return productFilterTypes.equalsAny;
            case 'contains':
                return productFilterTypes.notContains;
            case 'notContains':
                return productFilterTypes.contains;
            default:
                return productFilterTypes[type] || null;
        }
    }

    function isNegatedType(type) {
        return [
            productFilterTypes.notContains.identifier,
            productFilterTypes.notEqualsAny.identifier,
            productFilterTypes.notEquals.identifier
        ].includes(type);
    }

    function isRangeType(type) {
        return [
            productFilterTypes.lessThan.identifier,
            productFilterTypes.lessThanEquals.identifier,
            productFilterTypes.greaterThan.identifier,
            productFilterTypes.greaterThanEquals.identifier,
            productFilterTypes.range.identifier
        ].includes(type);
    }
}
