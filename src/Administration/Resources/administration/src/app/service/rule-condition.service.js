/**
 *
 * @memberOf module:core/service/login
 * @constructor
 * @method createConditionService
 * @returns {Object}
 */
export default function createConditionService() {
    const $store = {};
    const operators = {
        lowerThanEquals: {
            identifier: '<=',
            label: 'global.sw-condition-group.operator.lowerThanEquals'
        },
        equals: {
            identifier: '==',
            label: 'global.sw-condition-group.operator.equals'
        },
        greaterThanEquals: {
            identifier: '>=',
            label: 'global.sw-condition-group.operator.greaterThanEquals'
        },
        lowerThan: {
            identifier: '<',
            label: 'global.sw-condition-group.operator.lower'
        },
        greaterThan: {
            identifier: '>',
            label: 'global.sw-condition-group.operator.greater'
        },
        notEquals: {
            identifier: '!=',
            label: 'global.sw-condition-group.operator.notEquals'
        },
        isOneOf: {
            identifier: '=',
            label: 'global.sw-condition-group.operator.isOneOf'
        },
        isNoneOf: {
            identifier: '!=',
            label: 'global.sw-condition-group.operator.isNoneOf'
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
            operators.greaterThanEquals,
            operators.greaterThan,
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
        operators
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
}
