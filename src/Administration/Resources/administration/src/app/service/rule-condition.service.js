/**
 * @module app/service/rule-condition
 */

/**
 * @memberOf module:app/service/rule-condition
 * @constructor
 * @method createConditionService
 * @returns {Object}
 */
export default function createConditionService() {
    const $store = {
    };

    const operators = {
        lowerThanEquals: {
            identifier: '<=',
            label: 'global.sw-condition.operator.lowerThanEquals'
        },
        equals: {
            identifier: '=',
            label: 'global.sw-condition.operator.equals'
        },
        greaterThanEquals: {
            identifier: '>=',
            label: 'global.sw-condition.operator.greaterThanEquals'
        },
        notEquals: {
            identifier: '!=',
            label: 'global.sw-condition.operator.notEquals'
        },
        greaterThan: {
            identifier: '>',
            label: 'global.sw-condition.operator.greaterThan'
        },
        lowerThan: {
            identifier: '<',
            label: 'global.sw-condition.operator.lowerThan'
        },
        isOneOf: {
            identifier: '=',
            label: 'global.sw-condition.operator.isOneOf'
        },
        isNoneOf: {
            identifier: '!=',
            label: 'global.sw-condition.operator.isNoneOf'
        }
    };
    const operatorSets = {
        defaultSet: [
            operators.equals,
            operators.notEquals,
            operators.greaterThanEquals,
            operators.lowerThanEquals
        ],
        singleStore: [
            operators.equals,
            operators.notEquals
        ],
        multiStore: [
            operators.isOneOf,
            operators.isNoneOf
        ],
        string: [
            operators.equals,
            operators.notEquals
        ],
        bool: [
            operators.equals
        ],
        number: [
            operators.equals,
            operators.greaterThan,
            operators.greaterThanEquals,
            operators.lowerThan,
            operators.lowerThanEquals,
            operators.notEquals
        ]
    };

    const moduleTypes = {
        shipping: {
            id: 'shipping',
            name: 'sw-settings-rule.detail.types.shipping'
        },
        payment: {
            id: 'payment',
            name: 'sw-settings-rule.detail.types.payment'
        },
        price: {
            id: 'price',
            name: 'sw-settings-rule.detail.types.price'
        }
    };

    return {
        getByType,
        addCondition,
        getConditions,
        operatorSets,
        operators,
        addModuleType,
        getModuleTypes,
        getOperatorSet,
        getPlaceholder
    };

    function getByType(type) {
        return $store[type];
    }

    function addCondition(type, condition) {
        condition.type = type;
        $store[type] = condition;
    }

    function getOperatorSet(operatorSetName, translateCallback) {
        const operatorSet = this.operatorSets[operatorSetName];

        return translateData(operatorSet, translateCallback);
    }

    function addModuleType(type) {
        moduleTypes[type.id] = type;
    }

    function getModuleTypes(translateCallback) {
        const values = Object.values(moduleTypes);

        return translateData(values, translateCallback);
    }

    function getConditions(translateCallback, allowedScopes = null) {
        let values = Object.values($store);

        if (allowedScopes !== null) {
            values = values.filter(condition => {
                return allowedScopes.some(scope => condition.scopes.indexOf(scope) !== -1);
            });
        }

        return translateData(values, translateCallback);
    }

    function translateData(values, translateCallback) {
        if (translateCallback) {
            values.forEach(value => translateCallback(value));
        }

        return values;
    }

    function getPlaceholder() {
        return getByType('placeholder');
    }
}
