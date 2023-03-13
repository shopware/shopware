const { Criteria } = Shopware.Data;

/**
 * @module app/service/rule-condition
 */

/**
 * @private
 * @package business-ops
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
        flow: {
            id: 'flow',
            name: 'sw-settings-rule.detail.groups.flow',
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
        addScriptConditions,
        getConditions,
        addModuleType,
        getModuleTypes,
        getGroups,
        upsertGroup,
        removeGroup,
        getOperatorSet,
        getOperatorSetByComponent,
        getOperatorOptionsByIdentifiers,
        getAndContainerData,
        isAndContainer,
        getOrContainerData,
        isOrContainer,
        getPlaceholderData,
        getComponentByCondition,
        addEmptyOperatorToOperatorSet,
        addAwarenessConfiguration,
        getAwarenessConfigurationByAssignmentName,
        getRestrictedRules,
        getRestrictedConditions,
        getRestrictedAssociations,
        getRestrictionsByAssociation,
        getTranslatedConditionViolationList,
        getRestrictedRuleTooltipConfig,
        isRuleRestricted,
        getRestrictionsByGroup,
    };

    function getByType(type) {
        if (!type) {
            return getByType('placeholder');
        }

        if (type === 'scriptRule') {
            const scriptRule = getConditions().filter((condition) => {
                return condition.type === 'scriptRule';
            }).shift();

            if (scriptRule) {
                return scriptRule;
            }
        }

        return $store[type];
    }

    function addCondition(type, condition) {
        condition.type = type;
        $store[condition.scriptId ?? type] = condition;
    }

    function addScriptConditions(scripts) {
        scripts.forEach((script) => {
            addCondition('scriptRule', {
                component: 'sw-condition-script',
                label: script?.translated?.name || script.name,
                scopes: script.group === 'item' ? ['global', 'lineItem'] : ['global'],
                group: script.group,
                scriptId: script.id,
                appScriptCondition: {
                    id: script.id,
                    config: script.config,
                },
            });
        });
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

    function getOperatorOptionsByIdentifiers(identifiers, isMatchAny = false) {
        return identifiers.map((identifier) => {
            const option = Object.entries(operators).find(([name, operator]) => {
                if (isMatchAny && ['equals', 'notEquals'].includes(name)) {
                    return false;
                }
                if (!isMatchAny && ['isOneOf', 'isNoneOf'].includes(name)) {
                    return false;
                }

                return identifier === operator.identifier;
            });

            if (option) {
                return option.pop();
            }

            return {
                identifier,
                label: `global.sw-condition.operator.${identifier}`,
            };
        });
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

    function addAwarenessConfiguration(assignmentName, configuration) {
        awarenessConfiguration[assignmentName] = configuration;
        configuration.equalsAny = configuration.equalsAny?.filter(value => !configuration.notEquals?.includes(value));
    }

    function getAwarenessConfigurationByAssignmentName(assignmentName) {
        const config = awarenessConfiguration[assignmentName];

        return config || null;
    }

    /**
     * @param {Entity} rule
     * @returns {Object}
     * {
     *     conditionName: [
     *         { associationName: "association", snippet: "sw.snippet.association"},
     *         { associationName: "otherAssociation", snippet: "sw.snippet.otherAssociation"},
     *     ]
     * }
     */
    function getRestrictedConditions(rule) {
        if (!rule) {
            return {};
        }

        const keys = Object.keys(awarenessConfiguration);

        const conditions = {};
        keys.forEach(key => {
            const association = rule[key];
            const currentEntry = awarenessConfiguration[key];

            if (association && association.length > 0 && currentEntry.notEquals) {
                currentEntry.notEquals.forEach(condition => {
                    if (!conditions[condition]) {
                        conditions[condition] = [];
                    }
                    conditions[condition].push({
                        associationName: key,
                        snippet: currentEntry.snippet,
                    });
                });
            }
        });

        if (!rule.flowSequences?.length > 0) {
            return conditions;
        }

        rule.flowSequences.forEach(sequence => {
            const eventName = `flowTrigger.${sequence.flow.eventName}`;
            const currentEntry = awarenessConfiguration[eventName];

            if (!currentEntry?.notEquals) {
                return;
            }

            currentEntry.notEquals.forEach(condition => {
                if (!conditions[condition]) {
                    conditions[condition] = [];
                }
                conditions[condition].push({
                    associationName: eventName,
                    snippet: currentEntry.snippet,
                });
            });
        });

        return conditions;
    }

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
        const criteria = new Criteria(1, 25);
        criteria.addFilter(Criteria.multi(
            'OR',
            restrictions,
        ));

        return ruleRepository.searchIds(criteria).then(result => result.data);
    }

    /**
     * Checks the conditions with the current awareness configuration for the assignment name
     * and returns the result.
     * @param {EntityCollection} conditions
     * @param {String} assignmentName
     * @returns {object}
     * Example return: {
     *      assignmentName: assignmentName,
     *      notEqualsViolations: [{}, {}],
     *      equalsAnyMatched: [{}, {}],
     *      equalsAnyNotMatched: [{}, {}],
     *      isRestricted: false
     * }
     */
    function getRestrictionsByAssociation(conditions, assignmentName) {
        const awarenessEntry = getAwarenessConfigurationByAssignmentName(assignmentName);
        const restrictionConfig = {
            assignmentName: assignmentName,
            notEqualsViolations: [],
            equalsAnyMatched: [],
            equalsAnyNotMatched: [],
            isRestricted: false,
        };
        if (!awarenessEntry) {
            return restrictionConfig;
        }

        restrictionConfig.assignmentSnippet = awarenessEntry.snippet;

        if (awarenessEntry.notEquals) {
            conditions.forEach(condition => {
                if (awarenessEntry.notEquals.includes(condition.type)) {
                    restrictionConfig.notEqualsViolations.push(getByType(condition.type));
                    restrictionConfig.isRestricted = true;
                }
            });
        }

        if (awarenessEntry.equalsAny) {
            awarenessEntry.equalsAny.forEach(type => {
                const matchedCondition = conditions.find(condition => {
                    return condition.type === type;
                });
                if (matchedCondition) {
                    restrictionConfig.equalsAnyMatched.push(getByType(type));
                } else {
                    restrictionConfig.equalsAnyNotMatched.push(getByType(type));
                }
            });

            if (restrictionConfig.equalsAnyMatched.length === 0) {
                restrictionConfig.isRestricted = true;
            }
        }

        return restrictionConfig;
    }

    /**
     * Checks the conditions with the current awareness configuration and returns the result for
     * every assignment name.
     * @param {EntityCollection} conditions
     * @returns {object}
     * Example return: {
     *     associationName: {
     *         assignmentName: assignmentName,
     *         notEqualsViolations: [{}, {}],
     *         equalsAnyMatched: [{}, {}],
     *         equalsAnyNotMatched: [{}, {}],
     *         isRestricted: false
     *     },
     *     secondAssociationName: {
     *         assignmentName: assignmentName,
     *         notEqualsViolations: [{}, {}],
     *         equalsAnyMatched: [{}, {}],
     *         equalsAnyNotMatched: [{}, {}],
     *         isRestricted: false
     *     },
     * }
     */
    function getRestrictedAssociations(conditions) {
        if (!conditions) {
            return {};
        }
        const keys = Object.keys(awarenessConfiguration);
        const restrictedAssociations = {};

        keys.forEach(key => {
            restrictedAssociations[key] = getRestrictionsByAssociation(conditions, key);
        });

        return restrictedAssociations;
    }

    /**
     * Translates a list of violations and return the translated text
     * @param {array} violations
     * @param {string} connectionSnippetPath
     * @returns {string}
     */
    function getTranslatedConditionViolationList(violations, connectionSnippetPath) {
        const app = Shopware.Application.getApplicationRoot();
        let text = '';
        violations.forEach((violation, index, allViolations) => {
            text += `"${app.$tc(violation.label, 1)}"`;
            if (index + 2 === allViolations.length) {
                text += ` ${app.$tc(connectionSnippetPath)} `;
            } else if (index + 1 < allViolations.length) {
                text += ', ';
            }
        });
        return text;
    }

    /**
     *
     * @param {EntityCollection} ruleConditions
     * @param {string|null} ruleAwareGroupKey
     * @returns {object}
     */
    function getRestrictedRuleTooltipConfig(ruleConditions, ruleAwareGroupKey) {
        const app = Shopware.Application.getApplicationRoot();

        if (!ruleAwareGroupKey) {
            return { message: '', disabled: true };
        }

        const restrictionConfig = this.getRestrictionsByAssociation(
            ruleConditions,
            ruleAwareGroupKey,
        );

        if (!restrictionConfig.isRestricted) {
            return { message: '', disabled: true };
        }

        if (restrictionConfig.notEqualsViolations?.length > 0) {
            return {
                showOnDisabledElements: true,
                disabled: false,
                message: app.$tc(
                    'sw-restricted-rules.restrictedAssignment.notEqualsViolationTooltip',
                    {},
                    {
                        conditions: this.getTranslatedConditionViolationList(
                            restrictionConfig.notEqualsViolations,
                            'sw-restricted-rules.and',
                        ),
                        entityLabel: app.$tc(restrictionConfig.assignmentSnippet, 2),
                    },
                ),
            };
        }

        return {
            showOnDisabledElements: true,
            disabled: false,
            width: 400,
            message: app.$tc(
                'sw-restricted-rules.restrictedAssignment.equalsAnyViolationTooltip',
                0,
                {
                    conditions: this.getTranslatedConditionViolationList(
                        restrictionConfig.equalsAnyNotMatched,
                        'sw-restricted-rules.or',
                    ),
                    entityLabel: app.$tc(restrictionConfig.assignmentSnippet, 2),
                },
            ),
        };
    }

    /**
     *
     * @param {EntityCollection} ruleConditions
     * @param {string|null} ruleAwareGroupKey
     * @returns {boolean}
     */
    function isRuleRestricted(ruleConditions, ruleAwareGroupKey) {
        if (!ruleAwareGroupKey) {
            return false;
        }

        const restrictionConfig = this.getRestrictionsByAssociation(
            ruleConditions,
            ruleAwareGroupKey,
        );

        return restrictionConfig.isRestricted;
    }

    function getRestrictionsByGroup(...wantedGroups) {
        const entries = Object.entries($store);

        return entries.reduce((accumulator, [restrictionName, condition]) => {
            const inGroup = wantedGroups.includes(condition.group);

            return inGroup ? [...accumulator, restrictionName] : accumulator;
        }, []);
    }
}
