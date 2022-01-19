const { Criteria } = Shopware.Data;

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
    const $store = {};

    const awarenessConfiguration = {};

    const operators = {
        lowerThanEquals: {
            identifier: '<=',
            label: 'global.sw-condition.operator.lowerThanEquals',
        },
        equals: {
            identifier: '=',
            label: 'global.sw-condition.operator.equals',
        },
        greaterThanEquals: {
            identifier: '>=',
            label: 'global.sw-condition.operator.greaterThanEquals',
        },
        notEquals: {
            identifier: '!=',
            label: 'global.sw-condition.operator.notEquals',
        },
        greaterThan: {
            identifier: '>',
            label: 'global.sw-condition.operator.greaterThan',
        },
        lowerThan: {
            identifier: '<',
            label: 'global.sw-condition.operator.lowerThan',
        },
        isOneOf: {
            identifier: '=',
            label: 'global.sw-condition.operator.isOneOf',
        },
        isNoneOf: {
            identifier: '!=',
            label: 'global.sw-condition.operator.isNoneOf',
        },
        gross: {
            identifier: false,
            label: 'global.sw-condition.operator.gross',
        },
        net: {
            identifier: true,
            label: 'global.sw-condition.operator.net',
        },
        empty: {
            identifier: 'empty',
            label: 'global.sw-condition.operator.empty',
        },
    };
    const operatorSets = {
        defaultSet: [
            operators.equals,
            operators.notEquals,
            operators.greaterThanEquals,
            operators.lowerThanEquals,
        ],
        singleStore: [
            operators.equals,
            operators.notEquals,
        ],
        multiStore: [
            operators.isOneOf,
            operators.isNoneOf,
        ],
        string: [
            operators.equals,
            operators.notEquals,
        ],
        bool: [
            operators.equals,
        ],
        number: [
            operators.equals,
            operators.greaterThan,
            operators.greaterThanEquals,
            operators.lowerThan,
            operators.lowerThanEquals,
            operators.notEquals,
        ],
        date: [
            operators.equals,
            operators.greaterThan,
            operators.greaterThanEquals,
            operators.lowerThan,
            operators.lowerThanEquals,
            operators.notEquals,
        ],
        isNet: [
            operators.gross,
            operators.net,
        ],
        empty: [
            operators.empty,
        ],
        zipCode: [
            operators.greaterThan,
            operators.greaterThanEquals,
            operators.lowerThan,
            operators.lowerThanEquals,
        ],
    };

    const moduleTypes = {
        shipping: {
            id: 'shipping',
            name: 'sw-settings-rule.detail.types.shipping',
        },
        payment: {
            id: 'payment',
            name: 'sw-settings-rule.detail.types.payment',
        },
        price: {
            id: 'price',
            name: 'sw-settings-rule.detail.types.price',
        },
        flow: {
            id: 'flow',
            name: 'sw-settings-rule.detail.types.flowBuilder',
        },
    };

    const groups = {
        general: {
            id: 'general',
            name: 'sw-settings-rule.detail.groups.general',
        },
        cart: {
            id: 'cart',
            name: 'sw-settings-rule.detail.groups.cart',
        },
        items: {
            id: 'item',
            name: 'sw-settings-rule.detail.groups.items',
        },
        customers: {
            id: 'customer',
            name: 'sw-settings-rule.detail.groups.customers',
        },
        promotions: {
            id: 'promotion',
            name: 'sw-settings-rule.detail.groups.promotions',
        },
        misc: {
            id: 'misc',
            name: 'sw-settings-rule.detail.groups.misc',
        },
    };

    return {
        getByType,
        getByGroup,
        addCondition,
        getConditions,
        addModuleType,
        getModuleTypes,
        getGroups,
        upsertGroup,
        removeGroup,
        getOperatorSet,
        getOperatorSetByComponent,
        getAndContainerData,
        isAndContainer,
        getOrContainerData,
        isOrContainer,
        getPlaceholderData,
        getComponentByCondition,
        addEmptyOperatorToOperatorSet,
        /* @internal (flag:FEATURE_NEXT_18215) */
        addAwarenessConfiguration,
        /* @internal (flag:FEATURE_NEXT_18215) */
        getAwarenessConfigurationByAssignmentName,
        /* @internal (flag:FEATURE_NEXT_18215) */
        getRestrictedRules,
        /* @internal (flag:FEATURE_NEXT_18215) */
        getRestrictedConditions,
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

    function addEmptyOperatorToOperatorSet(operatorSet) {
        return operatorSet.concat(operatorSets.empty);
    }

    function getOperatorSetByComponent(component) {
        const componentName = component.config.componentName;
        const type = component.type;

        if (componentName === 'sw-single-select') {
            return operatorSets.singleStore;
        }
        if (componentName === 'sw-multi-select') {
            return operatorSets.multiStore;
        }
        if (type === 'bool') {
            return operatorSets.bool;
        }
        if (type === 'text') {
            return operatorSets.string;
        }
        if (type === 'int') {
            return operatorSets.number;
        }

        return operatorSets.defaultSet;
    }

    function addModuleType(type) {
        moduleTypes[type.id] = type;
    }

    function getModuleTypes() {
        return Object.values(moduleTypes);
    }

    function getByGroup(group) {
        const values = Object.values($store);
        const conditions = [];

        values.forEach(condition => {
            if (condition.group === group) {
                conditions.push(condition);
            }
        });

        return conditions;
    }

    function getGroups() {
        return groups;
    }

    function upsertGroup(groupName, groupData) {
        groups[groupName] = { ...groups[groupName], ...groupData };
    }

    function removeGroup(groupName) {
        delete groups[groupName];
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

    function isAllLineItemsContainer(condition) {
        return condition.type === 'allLineItemsContainer';
    }

    function getComponentByCondition(condition) {
        if (isAndContainer(condition)) {
            return 'sw-condition-and-container';
        }

        if (isOrContainer(condition)) {
            return 'sw-condition-or-container';
        }

        if (isAllLineItemsContainer(condition)) {
            return 'sw-condition-all-line-items-container';
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

    /* @internal (flag:FEATURE_NEXT_18215) */
    function addAwarenessConfiguration(assignmentName, configuration) {
        awarenessConfiguration[assignmentName] = configuration;
    }

    /* @internal (flag:FEATURE_NEXT_18215) */
    function getAwarenessConfigurationByAssignmentName(assignmentName) {
        const config = awarenessConfiguration[assignmentName];

        return config || {};
    }

    /* @internal (flag:FEATURE_NEXT_18215) */
    function getRestrictedConditions(rule) {
        const keys = Object.keys(awarenessConfiguration);

        const conditions = {};
        keys.forEach(key => {
            const association = rule[key];
            const currentEntry = awarenessConfiguration[key];

            if (association && currentEntry.notEquals) {
                currentEntry.notEquals.forEach(condition => {
                    conditions[condition] = {
                        snippet: currentEntry.snippet,
                    };
                });
            }
        });

        return conditions;
    }

    /* @internal (flag:FEATURE_NEXT_18215) */
    function getRestrictedRules(entityName) {
        const configuration = getAwarenessConfigurationByAssignmentName(entityName);

        if (!configuration) {
            return Promise.resolve([]);
        }

        const { notEquals, equalsAny } = configuration;
        const restrictions = [];

        if (notEquals) {
            restrictions.push(Criteria.equalsAny('conditions.type', notEquals));
        }

        if (equalsAny) {
            restrictions.push(Criteria.not('AND', [Criteria.equalsAny('conditions.type', equalsAny)]));
        }

        if (restrictions.length === 0) {
            return Promise.resolve([]);
        }

        const ruleRepository = Shopware.Service('repositoryFactory').create('rule');
        const criteria = new Criteria();
        criteria.addFilter(Criteria.multi(
            'OR',
            restrictions,
        ));

        return ruleRepository.searchIds(criteria).then(result => result.data);
    }
}
