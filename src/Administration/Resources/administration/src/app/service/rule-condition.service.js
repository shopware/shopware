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
        placeholder: {
            type: 'placeholder',
            component: 'sw-condition-base',
            label: 'global.sw-condition.condition.base'
        }
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

    const moduleTypes = [
        'dispatch',
        'payment',
        'price'
    ];

    return {
        getByType,
        addCondition,
        getConditions,
        operatorSets,
        operators,
        moduleTypes,
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

        if (translateCallback) {
            operatorSet.forEach(operator => translateCallback(operator));
        }

        return operatorSet;
    }

    function getConditions(translateCallback, modules) {
        // return defaults only if no modules are set
        if (modules.length === 0) {
            const conditions = Object.values($store).filter((condition) => {
                return !condition.modules;
            });
            return translateConditions(conditions, translateCallback);
        }

        const conditions = Object.values($store).filter((condition) => {
            // always include object that have no modules property set
            if (!condition.modules) {
                return true;
            }

            // include objects that fit the modules parameter
            return condition.modules.some((storeModule) => {
                return modules.includes(storeModule);
            });
        });

        return translateConditions(conditions, translateCallback);
    }

    function translateConditions(conditions, translateCallback) {
        if (translateCallback) {
            conditions.forEach((condition) => {
                translateCallback(condition);
            });
        }
        return conditions;
    }

    function getPlaceholder() {
        return getByType('placeholder');
    }
}
