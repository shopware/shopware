import type EntityCollection from '@shopware-ag/admin-extension-sdk/es/data/_internals/EntityCollection';

const { Criteria } = Shopware.Data;

type appScriptCondition = {
    id: string,
    config: unknown
}

type condition = {
    type: string,
    component: string,
    label: string,
    scopes: string[],
    group: string,
    scriptId: string,
    appScriptCondition: appScriptCondition,
}

type script = {
    id: string,
    name?: string,
    translated?: {
        name?: string,
    },
    group: string,
    config: unknown,
}

type operatorSetIdentifier =
    'defaultSet' |
    'singleStore' |
    'multiStore' |
    'string' |
    'bool' |
    'number' |
    'date' |
    'isNet' |
    'empty' |
    'zipCode';

type component = {
    type: string,
    config: {
        componentName: string,
    },
}

type moduleType = {
    id: string,
    name: string,
}

type group = {
    id: string,
    name: string,
}

type awarenessConfiguration = {
    notEquals?: Array<string>,
    equalsAny?: Array<string>,
    snippet?: string,
}

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
export default class RuleConditionService {
    $store: { [key: string]: condition} = {};

    awarenessConfiguration: { [key: string]: awarenessConfiguration} = {};

    operators = {
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

    operatorSets = {
        defaultSet: [
            this.operators.equals,
            this.operators.notEquals,
            this.operators.greaterThanEquals,
            this.operators.lowerThanEquals,
        ],
        singleStore: [
            this.operators.equals,
            this.operators.notEquals,
        ],
        multiStore: [
            this.operators.isOneOf,
            this.operators.isNoneOf,
        ],
        string: [
            this.operators.equals,
            this.operators.notEquals,
        ],
        bool: [
            this.operators.equals,
        ],
        number: [
            this.operators.equals,
            this.operators.greaterThan,
            this.operators.greaterThanEquals,
            this.operators.lowerThan,
            this.operators.lowerThanEquals,
            this.operators.notEquals,
        ],
        date: [
            this.operators.equals,
            this.operators.greaterThan,
            this.operators.greaterThanEquals,
            this.operators.lowerThan,
            this.operators.lowerThanEquals,
            this.operators.notEquals,
        ],
        isNet: [
            this.operators.gross,
            this.operators.net,
        ],
        empty: [
            this.operators.empty,
        ],
        zipCode: [
            this.operators.greaterThan,
            this.operators.greaterThanEquals,
            this.operators.lowerThan,
            this.operators.lowerThanEquals,
        ],
    };

    moduleTypes: { [key: string]: moduleType } = {
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

    groups: { [key: string]: group} = {
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

    getByType(type: string): condition {
        if (!type) {
            return this.getByType('placeholder');
        }

        if (type === 'scriptRule') {
            const scriptRule = this.getConditions().filter((condition) => {
                return condition.type === 'scriptRule';
            }).shift();

            if (scriptRule) {
                return scriptRule;
            }
        }

        return this.$store[type];
    }

    addCondition(type: string, condition: Partial<Omit<condition, 'type'>>) {
        (condition as condition).type = type;

        this.$store[condition.scriptId ?? type] = condition as condition;
    }

    addScriptConditions(scripts: script[]) {
        scripts.forEach((script) => {
            this.addCondition('scriptRule', {
                component: 'sw-condition-script',
                label: (script?.translated?.name || script.name) ?? '',
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

    getOperatorSet(operatorSetName: operatorSetIdentifier) {
        return this.operatorSets[operatorSetName];
    }

    addEmptyOperatorToOperatorSet(operatorSet: Array<unknown>) {
        return operatorSet.concat(this.operatorSets.empty);
    }

    getOperatorSetByComponent(component: component) {
        const componentName = component.config.componentName;
        const type = component.type;

        if (componentName === 'sw-single-select') {
            return this.operatorSets.singleStore;
        }
        if (componentName === 'sw-multi-select') {
            return this.operatorSets.multiStore;
        }
        if (type === 'bool') {
            return this.operatorSets.bool;
        }
        if (type === 'text') {
            return this.operatorSets.string;
        }
        if (type === 'int') {
            return this.operatorSets.number;
        }

        return this.operatorSets.defaultSet;
    }

    getOperatorOptionsByIdentifiers(identifiers: Array<string>, isMatchAny = false) {
        return identifiers.map((identifier) => {
            const option = Object.entries(this.operators).find(([name, operator]) => {
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

    addModuleType(type: moduleType) {
        this.moduleTypes[type.id] = type;
    }

    getModuleTypes() {
        return Object.values(this.moduleTypes);
    }

    getByGroup(group: string) {
        const values = Object.values(this.$store);
        const conditions: Array<condition> = [];

        values.forEach(condition => {
            if (condition.group === group) {
                conditions.push(condition);
            }
        });

        return conditions;
    }

    getGroups() {
        return this.groups;
    }

    upsertGroup(groupName: string, groupData: group) {
        this.groups[groupName] = { ...this.groups[groupName], ...groupData };
    }

    removeGroup(groupName: string) {
        delete this.groups[groupName];
    }

    getConditions(allowedScopes: Array<string>|null = null): condition[] {
        let values = Object.values(this.$store);

        if (allowedScopes !== null) {
            values = values.filter(condition => {
                return allowedScopes.some(scope => condition.scopes.indexOf(scope) !== -1);
            });
        }

        return values;
    }

    getAndContainerData() {
        return { type: 'andContainer', value: {} };
    }

    isAndContainer(condition: condition) {
        return condition.type === 'andContainer';
    }

    getOrContainerData() {
        return { type: 'orContainer', value: {} };
    }

    isOrContainer(condition: condition) {
        return condition.type === 'orContainer';
    }

    getPlaceholderData() {
        return { type: null, value: {} };
    }

    isAllLineItemsContainer(condition: condition) {
        return condition.type === 'allLineItemsContainer';
    }

    getComponentByCondition(condition: condition) {
        if (this.isAndContainer(condition)) {
            return 'sw-condition-and-container';
        }

        if (this.isOrContainer(condition)) {
            return 'sw-condition-or-container';
        }

        if (this.isAllLineItemsContainer(condition)) {
            return 'sw-condition-all-line-items-container';
        }

        if (!condition.type) {
            return 'sw-condition-base';
        }

        const conditionType = this.getByType(condition.type);

        if (typeof conditionType === 'undefined' || !conditionType.component) {
            return 'sw-condition-not-found';
        }

        return conditionType.component;
    }

    addAwarenessConfiguration(assignmentName: string, configuration: awarenessConfiguration) {
        this.awarenessConfiguration[assignmentName] = configuration;
        configuration.equalsAny = configuration.equalsAny?.filter(value => !configuration.notEquals?.includes(value));
    }

    getAwarenessConfigurationByAssignmentName(assignmentName: string) {
        const config = this.awarenessConfiguration[assignmentName];

        return config || null;
    }

    getAwarenessKeysWithEqualsAnyConfig() {
        const equalsAnyConfigurations: Array<string> = [];
        Object.entries(this.awarenessConfiguration).forEach(([key, value]) => {
            if (value?.equalsAny?.length && value?.equalsAny?.length > 0) {
                equalsAnyConfigurations.push(key);
            }
        });

        return equalsAnyConfigurations;
    }

    /**
     * @param {Entity} r
     * @returns {Object}
     * {
     *     conditionName: [
     *         { associationName: "association", snippet: "sw.snippet.association"},
     *         { associationName: "otherAssociation", snippet: "sw.snippet.otherAssociation"},
     *     ]
     * }
     */
    getRestrictedConditions(r: EntitySchema.rule) {
        if (!r) {
            return {};
        }

        const keys = Object.keys(this.awarenessConfiguration);

        const conditions: { [key: string]: Array<unknown> } = {};
        keys.forEach(key => {
            const association = r[key as keyof EntitySchema.rule] as Array<unknown>;
            const currentEntry = this.awarenessConfiguration[key];

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

        if (!r.flowSequences || r.flowSequences?.length <= 0) {
            return conditions;
        }

        (r.flowSequences as EntityCollection<'flow_sequence'>).forEach(sequence => {
            const eventName = `flowTrigger.${sequence.flow?.eventName ?? ''}`;
            const currentEntry = this.awarenessConfiguration[eventName];

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

    getRestrictedRules(entityName: string) {
        const configuration = this.getAwarenessConfigurationByAssignmentName(entityName);

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
    getRestrictionsByAssociation(conditions: EntityCollection<'rule_condition'>, assignmentName: string) {
        const awarenessEntry = this.getAwarenessConfigurationByAssignmentName(assignmentName);
        const restrictionConfig: {
            notEqualsViolations: Array<{ label: string }>,
            equalsAnyNotMatched: Array<{ label: string }>,
            isRestricted: boolean,
            assignmentName: string,
            equalsAnyMatched: condition[],
            assignmentSnippet?: string,
        } = {
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
                if (awarenessEntry.notEquals?.includes(condition.type)) {
                    restrictionConfig.notEqualsViolations.push(this.getByType(condition.type));
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
                    restrictionConfig.equalsAnyMatched.push(this.getByType(type));
                } else {
                    restrictionConfig.equalsAnyNotMatched.push(this.getByType(type));
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
    getRestrictedAssociations(conditions: EntityCollection<'rule_condition'>) {
        if (!conditions) {
            return {};
        }
        const keys = Object.keys(this.awarenessConfiguration);
        const restrictedAssociations: { [key: string]: unknown} = {};

        keys.forEach(key => {
            restrictedAssociations[key] = this.getRestrictionsByAssociation(conditions, key);
        });

        return restrictedAssociations;
    }

    /**
     * Translates a list of violations and return the translated text
     * @param {array} violations
     * @param {string} connectionSnippetPath
     * @returns {string}
     */
    getTranslatedConditionViolationList(violations: Array<{ label: string }>, connectionSnippetPath: string) {
        const app = Shopware.Application.getApplicationRoot();
        if (!app) {
            return '';
        }

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
    getRestrictedRuleTooltipConfig(ruleConditions: EntityCollection<'rule_condition'>, ruleAwareGroupKey: string|null) {
        const app = Shopware.Application.getApplicationRoot();

        if (!app || !ruleAwareGroupKey) {
            return { message: '', disabled: true };
        }

        const restrictionConfig = this.getRestrictionsByAssociation(
            // @ts-expect-error
            ruleConditions as Array<condition>,
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
                    undefined,
                    {
                        conditions: this.getTranslatedConditionViolationList(
                            restrictionConfig.notEqualsViolations,
                            'sw-restricted-rules.and',
                        ),
                        entityLabel: app.$tc(restrictionConfig.assignmentSnippet as string, 2),
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
                    entityLabel: app.$tc(restrictionConfig.assignmentSnippet ?? '', 2),
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
    isRuleRestricted(ruleConditions: EntityCollection<'rule_condition'>, ruleAwareGroupKey: string|null) {
        if (!ruleAwareGroupKey) {
            return false;
        }

        const restrictionConfig = this.getRestrictionsByAssociation(
            ruleConditions,
            ruleAwareGroupKey,
        );

        return restrictionConfig.isRestricted;
    }

    getRestrictionsByGroup(...wantedGroups: Array<string>) {
        const entries = Object.entries(this.$store);

        return entries.reduce((acc, [restrictionName, condition]) => {
            const inGroup = wantedGroups.includes(condition.group);

            return inGroup ? [...acc, restrictionName] : acc;
        }, [] as Array<string>);
    }
}
