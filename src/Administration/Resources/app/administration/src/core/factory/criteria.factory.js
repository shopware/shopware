/**
 * A factory which provides you with an interface to create filter and search queries. Queries can be nested and exclude
 * certain fields. The resulting queries can be used in conjunction with our API services.
 *
 * @module core/factory/criteria
 * @deprecated 6.1
 * @example
 * CriteriaFactory.multi(
 *     'AND',
 *     CriteriaFactory.equals('product.name', 'example'),
 *     CriteriaFactory.equalsAny('product.name', ['shopware', 'shopware AG']),
 *     CriteriaFactory.range('product.age', {
 *         '>': 10,
 *         '>=': 9,
 *         '<': 20,
 *         '<=': 19
 *     ),
 *     CriteriaFactory.not(
 *         'OR'
 *         CriteriaFactory.equals('product.name', 'another example'),
 *         CriteriaFactory.equalsAny('product.name', ['example manufacturer', 'another manufacturer'])
 *     ),
 *     CriteriaFactory.multi(
 *         'AND',
 *         CriteriaFactory.multi(
 *             'AND',
 *             CriteriaFactory.range('product.age', {
 *                 '>': 10
 *             }),
 *             CriteriaFactory.equals('product.manufacturer', 'yet another manufacturer')
 *         ),
 *         CriteriaFactory.multi(
 *             'AND',
 *             CriteriaFactory.range('product.age', {
 *                 '<': 50
 *             }),
 *             CriteriaFactory.equals('product.manufacturer', 'example manufacturer')
 *         )
 *     )
 * )
 *
 */
import types from 'src/core/service/utils/types.utils';

export default {
    equals: createEquals,
    multi: createMultiFilter,
    contains: createContains,
    range: createRange,
    not: createNot,
    equalsAny: createEqualsAny
};

/**
 * Aliases for multi and not operator.
 * @type {Object<Array>}
 */
const operatorAliases = {
    AND: ['and', '&&'],
    OR: ['or', '||']
};

/**
 * Aliases for the range operators
 * @type {Object<Array>}
 */
const rangeOperatorAliases = {
    lt: ['lt', '<'],
    lte: ['lte', '<='],
    gt: ['gt', '>'],
    gte: ['gte', '>=']
};

/**
 * Creates a new term query. If an array of values is provided as the second argument we automatically creating a
 * terms query instead of a term query.
 *
 * @example
 * CriteriaFactory.equals('product.name', 'example');
 *
 * @param {String} field
 * @param {String|Array} value
 * @returns {{getQueryString, getQuery}}
 */
function createEquals(field, value) {
    if (types.isArray(value)) {
        return createEqualsAny(field, value);
    }

    const query = {
        type: 'equals',
        field,
        value
    };
    return createOutputInterface(query);
}

/**
 * Creates a multi query. A multi query can either be used with an `AND` operator or `OR` operator.
 *
 * @example
 * CriteriaFactory.multi(
 *    'AND',
 *    CriteriaFactory.equals('product.name', 'Example'),
 *    CriteriaFactory.equals('product.manufacturer', 'shopware')
 * );
 *
 * @param {String} operator
 * @param {...Object} queries
 * @returns {{getQueryString, getQuery}}
 */
function createMultiFilter(operator, ...queries) {
    const query = {
        type: 'multi',
        operator: getOperator(operator),
        queries: mapQueries(queries)
    };
    return createOutputInterface(query);
}

/**
 * Creates a match query.
 *
 * @example
 * CriteriaFactory.contains('product.name', 'example');
 *
 * @param {String} field
 * @param {String} value
 * @returns {{getQueryString, getQuery}}
 */
function createContains(field, value) {
    const query = {
        type: 'contains',
        field,
        value
    };
    return createOutputInterface(query);
}

/**
 * Creates a not query which is useful for excluding queries.
 *
 * @example
 * CriteriaFactory.not(
 *    'AND',
 *    CriteriaFactory.equals('product.name', 'Example'),
 *    CriteriaFactory.equals('product.manufacturer', 'shopware')
 * );
 *
 * @param {String} operator
 * @param {...Object} queries
 * @returns {{getQueryString, getQuery}}
 */
function createNot(operator, ...queries) {
    const query = {
        type: 'not',
        operator: getOperator(operator),
        queries: mapQueries(queries)
    };
    return createOutputInterface(query);
}

/**
 * Creates a range query. Useful for price filtering for example
 *
 * @example
 * CriteriaFactory.range('product.age', {
 *    '>': 10,
 *    '>=': 9,
 *    '<': 20,
 *    '<=': 19
 * });
 *
 * @param {String} field
 * @param {Object} parameters
 * @returns {{getQueryString, getQuery}}
 */
function createRange(field, parameters) {
    const query = {
        type: 'range',
        parameters: reduceRangeParameters(parameters),
        field
    };
    return createOutputInterface(query);
}

/**
 * Creates a terms query. It's quite similar to a term query with the difference that it accepts multiple values for
 * one field.
 *
 * @example
 * CriteriaFactory.equalsAny('product.name', ['example', 'product']);
 *
 * @param {String} field
 * @param {Array} values
 * @returns {{getQueryString, getQuery}}
 */
function createEqualsAny(field, values) {
    const query = {
        type: 'equalsAny',
        field,
        value: values.join('|')
    };
    return createOutputInterface(query);
}

/**
 * Helper method which is used internally to map nested queries.
 *
 * @param {Array<{getQueryString, getQuery}>} queries
 * @returns {Array<Object>}
 */
function mapQueries(queries) {
    return queries.map((query) => query.getQuery());
}

/**
 * Helper method which is used internally for the range query. It maps the range operator to one of the aliases.
 *
 * @param {Object} parameters
 * @returns {Object}
 */
function reduceRangeParameters(parameters) {
    return Object.keys(parameters).reduce((remappedParameters, key) => {
        const operatorKey = getOperator(key, 'lt', rangeOperatorAliases);
        remappedParameters[operatorKey] = parameters[key];
        return remappedParameters;
    }, {});
}

/**
 * Helper method which resolves an operator based on the provided operator pool.
 *
 * @param {String} alias
 * @param {String} [defaultOperator='AND']
 * @param {Object} [operatorPool=operatorAliases]
 * @returns {String}
 */
function getOperator(alias, defaultOperator = 'AND', operatorPool = operatorAliases) {
    let operator = defaultOperator;

    Object.keys(operatorPool).every((key) => {
        const lowerQueryOperator = alias.toLowerCase();
        if (operatorPool[key].indexOf(lowerQueryOperator) !== -1) {
            operator = key;
            return false;
        }
        return true;
    });

    return operator;
}

/**
 * Helper method which creates will be used by every exposed function of the factory. It provided you with the ability
 * to get either a JSON stringified version of the query or a plain object.
 *
 * @param {Object} query
 * @returns {{getQueryString: String, getQuery: function(): Object}}
 */
function createOutputInterface(query) {
    return {
        getQueryString: () => getQueryString(query),
        getQuery: () => query
    };
}

/**
 * Returns a JSON stringified version of the provided query.
 *
 * @param {Object} query
 * @returns {String}
 */
function getQueryString(query) {
    return JSON.stringify(query);
}
