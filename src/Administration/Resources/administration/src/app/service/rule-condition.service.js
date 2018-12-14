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
        startsWith: {
            identifier: '%*',
            label: 'global.sw-condition-group.operator.startsWidth'
        },
        endsWith: {
            identifier: '*%',
            label: 'global.sw-condition-group.operator.endsWidth'
        },
        contains: {
            identifier: '*',
            label: 'global.sw-condition-group.operator.contains'
        },
        regex: {
            identifier: 'preg_match',
            label: 'global.sw-condition-group.operator.regex'
        },
        notEquals: {
            identifier: '!=',
            label: 'global.sw-condition-group.operator.notEquals'
        }
    };
    const operatorSets = {
        defaultSet: [
            operators.equals,
            operators.lowerThan,
            operators.greaterThan,
            operators.lowerThanEquals,
            operators.greaterThanEquals
        ],
        string: [
            operators.equals,
            operators.notEquals
        ],
        number: [
            operators.equals,
            operators.notEquals,
            operators.greaterThanEquals,
            operators.lowerThanEquals
        ],
        all: [
            ...Object.values(operators)
        ],
        test: [
            operators.equals,
            operators.lowerThan
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
