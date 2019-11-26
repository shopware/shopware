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
        addModuleType,
        getModuleTypes,
        getOperatorSet,
        getAndContainerData,
        isAndContainer,
        getOrContainerData,
        isOrContainer,
        getPlaceholderData,
        getComponentByCondition
    };

    function getByType(type) {
        if (!type) {
            return getByType('placeholder');
        }

        return $store[type];
    }

    function addCondition(type, condition) {
        condition.type = type;
        $store[type] = condition;
    }

    function getOperatorSet(operatorSetName) {
        return operatorSets[operatorSetName];
    }

    function addModuleType(type) {
        moduleTypes[type.id] = type;
    }

    function getModuleTypes() {
        return Object.values(moduleTypes);
    }

    function getConditions(allowedScopes = null) {
        let values = Object.values($store);

        if (allowedScopes !== null) {
            values = values.filter(condition => {
                return allowedScopes.some(scope => condition.scopes.indexOf(scope) !== -1);
            });
        }

        return values;
    }

    function getAndContainerData() {
        return { type: 'andContainer', value: {} };
    }

    function isAndContainer(condition) {
        return condition.type === 'andContainer';
    }

    function getOrContainerData() {
        return { type: 'orContainer', value: {} };
    }

    function isOrContainer(condition) {
        return condition.type === 'orContainer';
    }

    function getPlaceholderData() {
        return { type: null, value: {} };
    }

    function getComponentByCondition(condition) {
        if (isAndContainer(condition)) {
            return 'sw-condition-and-container';
        }

        if (isOrContainer(condition)) {
            return 'sw-condition-or-container';
        }

        if (!condition.type) {
            return 'sw-condition-base';
        }

        const conditionType = getByType(condition.type);

        if (typeof conditionType === 'undefined' || !conditionType.component) {
            return 'sw-condition-not-found';
        }

        return conditionType.component;
    }
}
