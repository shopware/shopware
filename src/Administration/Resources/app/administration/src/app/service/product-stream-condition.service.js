const utils = Shopware.Utils;

/**
 * @module app/service/product-stream-condition
 */

/**
 * @private
 * @package business-ops
 * @memberOf module:app/service/product-stream-condition
 * @constructor
 * @method conditionService
 * @returns {Object}
 */
export default function conditionService() {
    const allowedProperties = [
        'id',
    ];

    const entityAllowedProperties = {
        tag: [
            'id',
        ],
        category: [
            'id',
        ],
        product_manufacturer: [
            'id',
        ],
        property_group_option: [
            'id',
            'group',
        ],
        property_group: [
            'id',
        ],
        product_visibility: [
            'id',
            'salesChannel',
        ],
        sales_channel: [
            'id',
        ],
        product: [
            'id',
            'active',
            'name',
            'description',
            'ratingAverage',
            'cheapestPrice',
            'productNumber',
            'stock',
            'availableStock',
            'releaseDate',
            'tags',
            'weight',
            'height',
            'width',
            'length',
            'ean',
            'sales',
            'manufacturer',
            'manufacturerNumber',
            'categoriesRo',
            'shippingFree',
            'visibilities',
            'properties',
            'options',
            'isCloseout',
            'deliveryTime',
            'purchasePrices',
            'createdAt',
            'coverId',
            'markAsTopseller',
            'states',
        ],
    };

    const allowedJsonAccessors = {
        cheapestPrice: {
            value: 'cheapestPrice',
            type: 'float',
            trans: 'cheapestPrice',
        },
        'cheapestPrice.percentage': {
            value: 'cheapestPrice.percentage',
            type: 'float',
            trans: 'percentage',
        },
    };

    const productFilterTypes = {
        equals: {
            identifier: 'equals',
            label: 'sw-product-stream.filter.type.equals',
        },

        equalsAny: {
            identifier: 'equalsAny',
            label: 'sw-product-stream.filter.type.equalsAny',
        },

        contains: {
            identifier: 'contains',
            label: 'sw-product-stream.filter.type.contains',
        },

        lessThan: {
            identifier: 'lessThan',
            label: 'sw-product-stream.filter.type.lessThan',
        },

        greaterThan: {
            identifier: 'greaterThan',
            label: 'sw-product-stream.filter.type.greaterThan',
        },

        lessThanEquals: {
            identifier: 'lessThanEquals',
            label: 'sw-product-stream.filter.type.lessThanEquals',
        },

        greaterThanEquals: {
            identifier: 'greaterThanEquals',
            label: 'sw-product-stream.filter.type.greaterThanEquals',
        },

        notEquals: {
            identifier: 'notEquals',
            label: 'sw-product-stream.filter.type.notEquals',
        },

        notEqualsAny: {
            identifier: 'notEqualsAny',
            label: 'sw-product-stream.filter.type.notEqualsAny',
        },

        notContains: {
            identifier: 'notContains',
            label: 'sw-product-stream.filter.type.notContains',
        },

        range: {
            identifier: 'range',
            label: 'sw-product-stream.filter.type.range',
        },

        until: {
            identifier: 'until',
            label: 'sw-product-stream.filter.type.until',
            operators: ['equals', 'notEquals', 'lessThan', 'greaterThan', 'lessThanEquals', 'greaterThanEquals'],
        },

        since: {
            identifier: 'since',
            label: 'sw-product-stream.filter.type.since',
            operators: ['equals', 'notEquals', 'lessThan', 'greaterThan', 'lessThanEquals', 'greaterThanEquals'],
        },

        not: {
            identifier: 'not',
            label: 'sw-product-stream.filter.type.not',
        },

        equalsAll: {
            identifier: 'equalsAll',
            label: 'sw-product-stream.filter.type.equalsAll',
        },
        notEqualsAll: {
            identifier: 'notEqualsAll',
            label: 'sw-product-stream.filter.type.notEqualsAll',
        },
    };

    const operatorSets = {
        boolean: [
            productFilterTypes.equals,
        ],

        empty: [
            productFilterTypes.equals,
        ],

        string: [
            productFilterTypes.equals,
            productFilterTypes.notEquals,
            productFilterTypes.equalsAny,
            productFilterTypes.notEqualsAny,
            productFilterTypes.contains,
            productFilterTypes.notContains,
        ],

        date: [
            productFilterTypes.equals,
            productFilterTypes.greaterThan,
            productFilterTypes.greaterThanEquals,
            productFilterTypes.lessThan,
            productFilterTypes.lessThanEquals,
            productFilterTypes.notEquals,
            productFilterTypes.range,
            productFilterTypes.since,
            productFilterTypes.until,
        ],

        uuid: [
            productFilterTypes.equals,
            productFilterTypes.notEquals,
            productFilterTypes.equalsAny,
            productFilterTypes.notEqualsAny,
            productFilterTypes.equalsAll,
            productFilterTypes.notEqualsAll,
        ],

        int: [
            productFilterTypes.equals,
            productFilterTypes.greaterThan,
            productFilterTypes.greaterThanEquals,
            productFilterTypes.lessThan,
            productFilterTypes.lessThanEquals,
            productFilterTypes.notEquals,
            productFilterTypes.range,
        ],

        float: [
            productFilterTypes.equals,
            productFilterTypes.greaterThan,
            productFilterTypes.greaterThanEquals,
            productFilterTypes.lessThan,
            productFilterTypes.lessThanEquals,
            productFilterTypes.notEquals,
            productFilterTypes.range,
        ],

        object: [
            productFilterTypes.equals,
            productFilterTypes.greaterThan,
            productFilterTypes.greaterThanEquals,
            productFilterTypes.lessThan,
            productFilterTypes.lessThanEquals,
            productFilterTypes.notEquals,
            productFilterTypes.range,
        ],

        default: [
            productFilterTypes.equals,
            productFilterTypes.notEquals,
            productFilterTypes.equalsAny,
            productFilterTypes.notEqualsAny,
        ],
    };

    return {
        isPropertyInAllowList,
        addToGeneralAllowList,
        addToEntityAllowList,
        removeFromGeneralAllowList,
        removeFromEntityAllowList,
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
        isRangeType,
        isRelativeTimeType,
        allowedJsonAccessors,
    };

    /**
     * @param {?string} definition
     * @param {string} property
     * @returns {boolean}
     */
    function isPropertyInAllowList(definition, property) {
        return allowedProperties.includes(property)
            || (entityAllowedProperties.hasOwnProperty(definition)
                && entityAllowedProperties[definition].includes(property)
            );
    }

    /**
     * @param {string|string[]} properties
     */
    function addToGeneralAllowList(properties) {
        properties = Array.isArray(properties) ? properties : [properties];
        allowedProperties.push(...properties);
    }

    /**
     * @param {string} entity
     * @param {string|string[]} properties
     */
    function addToEntityAllowList(entity, properties) {
        if (entityAllowedProperties[entity]) {
            properties = Array.isArray(properties) ? properties : [properties];
            entityAllowedProperties[entity].push(...properties);

            return;
        }

        entityAllowedProperties[entity] = properties;
    }

    /**
     * @param {string|string[]} properties
     */
    function removeFromGeneralAllowList(properties) {
        properties = Array.isArray(properties) ? properties : [properties];
        properties.forEach(entry => {
            allowedProperties.splice(allowedProperties.indexOf(entry), 1);
        });
    }

    /**
     * @param {string} entity
     * @param {string|string[]} properties
     */
    function removeFromEntityAllowList(entity, properties) {
        if (!entityAllowedProperties[entity]) {
            return;
        }

        properties = Array.isArray(properties) ? properties : [properties];
        properties.forEach(entry => {
            entityAllowedProperties[entity].splice(entityAllowedProperties[entity].indexOf(entry), 1);
        });
    }

    function getConditions() {
        return [
            {
                type: 'productStreamFilter',
                component: 'sw-product-stream-filter',
                label: 'product',
                scopes: ['product'],
            },
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
            case 'notEqualsAll':
                return productFilterTypes.equalsAll;
            case 'equalsAll':
                return productFilterTypes.notEqualsAll;
            default:
                return productFilterTypes[type] || null;
        }
    }

    function isNegatedType(type) {
        return [
            productFilterTypes.notContains.identifier,
            productFilterTypes.notEqualsAny.identifier,
            productFilterTypes.notEquals.identifier,
            productFilterTypes.notEqualsAll.identifier,
        ].includes(type);
    }

    function isRangeType(type) {
        return [
            productFilterTypes.lessThan.identifier,
            productFilterTypes.lessThanEquals.identifier,
            productFilterTypes.greaterThan.identifier,
            productFilterTypes.greaterThanEquals.identifier,
            productFilterTypes.range.identifier,
        ].includes(type);
    }

    function isRelativeTimeType(type) {
        return [
            productFilterTypes.since.identifier,
            productFilterTypes.until.identifier,
        ].includes(type);
    }
}
