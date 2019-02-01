/**
 *
 * @memberOf module:core/service/login
 * @constructor
 * @method createConditionService
 * @returns {Object}
 */
export default function createConditionService() {
    const $store = {
        placeholder: {
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

    return {
        getByType,
        addCondition,
        getConditions,
        operatorSets,
        operators,
        getPlaceholder
    };

    function getByType(type) {
        return $store[type];
    }

    function addCondition(type, condition) {
        $store[type] = condition;
    }

    function getConditions() {
        return $store;
    }

    function getPlaceholder() {
        return getByType('placeholder');
    }
}
