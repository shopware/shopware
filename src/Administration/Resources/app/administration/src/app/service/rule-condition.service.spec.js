/**
 * @package admin
 */
import RuleConditionService from 'src/app/service/rule-condition.service';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search-ids/rule',
    status: 200,
    response: {
        data: ['restricted-rule-id'],
    },
});

describe('src/app/service/rule-condition.service.js', () => {
    it('should be a function', async () => {
        expect(typeof RuleConditionService).toBe('function');
    });

    it('should return restricted rules', async () => {
        const ruleConditionService = new RuleConditionService();
        ruleConditionService.addAwarenessConfiguration('personaPromotions', {
            notEquals: ['cartCartAmount'],
        });

        const result = await ruleConditionService.getRestrictedRules('personaPromotions');
        expect(result).toEqual(['restricted-rule-id']);
    });

    it('should return empty array when the specified relation does not exist', async () => {
        const ruleConditionService = new RuleConditionService();

        const result = await ruleConditionService.getRestrictedRules('personaPromotions');
        expect(result).toEqual([]);
    });

    it('should return restricted conditions', async () => {
        const ruleConditionService = new RuleConditionService();
        ruleConditionService.addAwarenessConfiguration('personaPromotions', {
            notEquals: ['cartCartAmount'],
            snippet: 'random-snippet',
        });

        const rule = {
            personaPromotions: [{ id: 'someId' }],
        };

        const restrictedConditions = ruleConditionService.getRestrictedConditions(rule);

        expect(restrictedConditions).toEqual({
            cartCartAmount: [
                {
                    associationName: 'personaPromotions',
                    snippet: 'random-snippet',
                },
            ],
        });
    });

    it('should add config item', async () => {
        const ruleConditionService = new RuleConditionService();

        const configItemBefore = ruleConditionService.getAwarenessConfigurationByAssignmentName('personaPromotions');

        expect(configItemBefore).toBeNull();

        ruleConditionService.addAwarenessConfiguration('personaPromotions', {
            notEquals: ['cartCartAmount'],
        });

        const configItemAfter = ruleConditionService.getAwarenessConfigurationByAssignmentName('personaPromotions');

        expect(configItemAfter).toEqual({
            notEquals: ['cartCartAmount'],
        });
    });

    it('should get config item', async () => {
        const ruleConditionService = new RuleConditionService();
        ruleConditionService.addAwarenessConfiguration('personaPromotions', {
            notEquals: ['cartCartAmount'],
        });

        const configItem = ruleConditionService.getAwarenessConfigurationByAssignmentName('personaPromotions');

        expect(configItem).toEqual({
            notEquals: ['cartCartAmount'],
        });
    });

    it('should return empty object when the rule is undefined', async () => {
        const ruleConditionService = new RuleConditionService();
        const restricted = ruleConditionService.getRestrictedConditions();

        expect(restricted).toEqual({});
    });

    it('should return empty config when assignmentName is not in the config', async () => {
        const ruleConditionService = new RuleConditionService();
        const restricted = ruleConditionService.getRestrictionsByAssociation([], 'assignmentName');

        expect(restricted).toEqual({
            assignmentName: 'assignmentName',
            notEqualsViolations: [],
            equalsAnyMatched: [],
            equalsAnyNotMatched: [],
            isRestricted: false,
        });
    });

    it('should return restriction config with restricted true by not equals restriction', async () => {
        const ruleConditionService = new RuleConditionService();

        ruleConditionService.addCondition('conditionType1', {});
        ruleConditionService.addCondition('conditionType2', {});
        ruleConditionService.addCondition('conditionType3', {});

        ruleConditionService.addAwarenessConfiguration('assignmentOne', {
            notEquals: ['conditionType1'],
            equalsAny: [
                'conditionType2',
                'conditionType3',
            ],
            snippet: 'sw-assignment-one-snippet',
        });

        const conditions = [
            { type: 'andContainer' },
            { type: 'conditionType1' },
            { type: 'conditionType2' },
        ];

        const restricted = ruleConditionService.getRestrictionsByAssociation(conditions, 'assignmentOne');

        expect(restricted.assignmentName).toBe('assignmentOne');
        expect(restricted.assignmentSnippet).toBe('sw-assignment-one-snippet');
        expect(restricted.isRestricted).toBeTruthy();
        expect(restricted.notEqualsViolations[0].type).toBe('conditionType1');
        expect(restricted.equalsAnyMatched[0].type).toBe('conditionType2');
        expect(restricted.equalsAnyNotMatched[0].type).toBe('conditionType3');
    });

    it('should return restriction config with restricted true by equals any restriction', async () => {
        const ruleConditionService = new RuleConditionService();

        ruleConditionService.addCondition('conditionType1', {});
        ruleConditionService.addCondition('conditionType2', {});
        ruleConditionService.addCondition('conditionType3', {});

        ruleConditionService.addAwarenessConfiguration('assignmentOne', {
            notEquals: ['conditionType1'],
            equalsAny: [
                'conditionType2',
                'conditionType3',
            ],
            snippet: 'sw-assignment-one-snippet',
        });

        const conditions = [
            { type: 'andContainer' },
            { type: 'conditionType4' },
        ];

        const restricted = ruleConditionService.getRestrictionsByAssociation(conditions, 'assignmentOne');

        expect(restricted.assignmentName).toBe('assignmentOne');
        expect(restricted.isRestricted).toBeTruthy();
        expect(restricted.notEqualsViolations).toHaveLength(0);
        expect(restricted.equalsAnyMatched).toHaveLength(0);
        expect(restricted.equalsAnyNotMatched).toHaveLength(2);
        expect(restricted.equalsAnyNotMatched[0].type).toBe('conditionType2');
        expect(restricted.equalsAnyNotMatched[1].type).toBe('conditionType3');
    });

    it('should return restriction config with restricted false', async () => {
        const ruleConditionService = new RuleConditionService();

        ruleConditionService.addCondition('conditionType1', {});
        ruleConditionService.addCondition('conditionType2', {});
        ruleConditionService.addCondition('conditionType3', {});

        ruleConditionService.addAwarenessConfiguration('assignmentOne', {
            notEquals: ['conditionType1'],
            equalsAny: [
                'conditionType2',
                'conditionType3',
            ],
            snippet: 'sw-assignment-one-snippet',
        });

        const conditions = [
            { type: 'andContainer' },
            { type: 'conditionType2' },
            { type: 'conditionType3' },
        ];

        const restricted = ruleConditionService.getRestrictionsByAssociation(conditions, 'assignmentOne');

        expect(restricted.assignmentName).toBe('assignmentOne');
        expect(restricted.isRestricted).toBeFalsy();
        expect(restricted.notEqualsViolations).toHaveLength(0);
        expect(restricted.equalsAnyMatched).toHaveLength(2);
        expect(restricted.equalsAnyNotMatched).toHaveLength(0);
    });

    it('should return restricted associations', async () => {
        const ruleConditionService = new RuleConditionService();

        ruleConditionService.addCondition('conditionType1', {});
        ruleConditionService.addCondition('conditionType2', {});
        ruleConditionService.addCondition('conditionType3', {});

        ruleConditionService.addAwarenessConfiguration('assignmentOne', {
            notEquals: ['conditionType1'],
            equalsAny: [
                'conditionType2',
                'conditionType3',
            ],
            snippet: 'sw-assignment-one-snippet',
        });

        ruleConditionService.addAwarenessConfiguration('assignmentTwo', {
            notEquals: ['conditionType2'],
            equalsAny: [
                'conditionType2',
                'conditionType3',
            ],
            snippet: 'sw-assignment-one-snippet',
        });

        const conditions = [
            { type: 'andContainer' },
            { type: 'conditionType2' },
            { type: 'conditionType3' },
        ];

        const restricted = ruleConditionService.getRestrictedAssociations(conditions);

        expect(restricted.assignmentOne.isRestricted).toBeFalsy();
        expect(restricted.assignmentTwo.isRestricted).toBeTruthy();
    });

    it('should return a translated list of violations', async () => {
        const ruleConditionService = new RuleConditionService();

        let translatedViolations = ruleConditionService.getTranslatedConditionViolationList(
            [
                { label: 'violation1' },
                { label: 'violation2' },
                { label: 'violation3' },
            ],
            'and',
        );
        expect(translatedViolations).toBe('"violation1", "violation2" and "violation3"');

        translatedViolations = ruleConditionService.getTranslatedConditionViolationList(
            [
                { label: 'violation1' },
            ],
            'and',
        );
        expect(translatedViolations).toBe('"violation1"');
    });

    it('should return a disabled restriction tooltip because of no violations', async () => {
        const ruleConditionService = new RuleConditionService();

        ruleConditionService.addAwarenessConfiguration('assignmentOne', {
            notEquals: ['conditionType1'],
            equalsAny: [
                'conditionType2',
                'conditionType3',
            ],
            snippet: 'sw-assignment-one-snippet',
        });

        const conditions = [
            { type: 'andContainer' },
            { type: 'conditionType2' },
            { type: 'conditionType3' },
        ];

        const tooltipConfig = ruleConditionService.getRestrictedRuleTooltipConfig(conditions, 'assignmentOne');
        expect(tooltipConfig.disabled).toBeTruthy();
        expect(tooltipConfig.message).toBeFalsy();
    });

    it('should return a disabled restriction tooltip because empty ruleAwareGroupKey', async () => {
        const ruleConditionService = new RuleConditionService();

        const tooltipConfig = ruleConditionService.getRestrictedRuleTooltipConfig([], undefined);
        expect(tooltipConfig.disabled).toBeTruthy();
        expect(tooltipConfig.message).toBeFalsy();
    });

    it('should return an enabled restriction tooltip by not equals violation', async () => {
        const ruleConditionService = new RuleConditionService();

        ruleConditionService.addAwarenessConfiguration('assignmentOne', {
            notEquals: ['conditionType1'],
            equalsAny: [
                'conditionType2',
                'conditionType3',
            ],
            snippet: 'sw-assignment-one-snippet',
        });

        ruleConditionService.addCondition('conditionType1', {
            label: 'conditionType1Label',
        });
        ruleConditionService.addCondition('conditionType2', {
            label: 'conditionType2Label',
        });
        ruleConditionService.addCondition('conditionType3', {
            label: 'conditionType3Label',
        });

        const conditions = [
            { type: 'andContainer' },
            { type: 'conditionType1' },
            { type: 'conditionType3' },
        ];

        const tooltipConfig = ruleConditionService.getRestrictedRuleTooltipConfig(conditions, 'assignmentOne');
        expect(tooltipConfig.disabled).toBeFalsy();
        expect(tooltipConfig.message).toBe('sw-restricted-rules.restrictedAssignment.notEqualsViolationTooltip');
    });

    it('should return an enabled restriction tooltip by equals any violation', async () => {
        const ruleConditionService = new RuleConditionService();

        ruleConditionService.addAwarenessConfiguration('assignmentOne', {
            notEquals: [],
            equalsAny: [
                'conditionType2',
                'conditionType3',
            ],
            snippet: 'sw-assignment-one-snippet',
        });

        ruleConditionService.addCondition('conditionType1', {
            label: 'conditionType1Label',
        });
        ruleConditionService.addCondition('conditionType2', {
            label: 'conditionType2Label',
        });
        ruleConditionService.addCondition('conditionType3', {
            label: 'conditionType3Label',
        });

        const conditions = [
            { type: 'andContainer' },
            { type: 'conditionType1' },
        ];

        const tooltipConfig = ruleConditionService.getRestrictedRuleTooltipConfig(conditions, 'assignmentOne');
        expect(tooltipConfig.disabled).toBeFalsy();
        expect(tooltipConfig.message).toBe('sw-restricted-rules.restrictedAssignment.equalsAnyViolationTooltip');
    });

    it('should be restricted', async () => {
        const ruleConditionService = new RuleConditionService();

        ruleConditionService.addAwarenessConfiguration('assignmentOne', {
            notEquals: [],
            equalsAny: [
                'conditionType2',
                'conditionType3',
            ],
            snippet: 'sw-assignment-one-snippet',
        });

        ruleConditionService.addCondition('conditionType1', {
            label: 'conditionType1Label',
        });
        ruleConditionService.addCondition('conditionType2', {
            label: 'conditionType2Label',
        });
        ruleConditionService.addCondition('conditionType3', {
            label: 'conditionType3Label',
        });

        const conditions = [
            { type: 'andContainer' },
            { type: 'conditionType1' },
        ];

        const result = ruleConditionService.isRuleRestricted(conditions, 'assignmentOne');
        expect(result).toBeTruthy();
    });

    it('should not be restricted', async () => {
        const ruleConditionService = new RuleConditionService();

        ruleConditionService.addAwarenessConfiguration('assignmentOne', {
            notEquals: [],
            equalsAny: [
                'conditionType2',
                'conditionType3',
            ],
            snippet: 'sw-assignment-one-snippet',
        });

        ruleConditionService.addCondition('conditionType1', {
            label: 'conditionType1Label',
        });
        ruleConditionService.addCondition('conditionType2', {
            label: 'conditionType2Label',
        });
        ruleConditionService.addCondition('conditionType3', {
            label: 'conditionType3Label',
        });

        const conditions = [
            { type: 'andContainer' },
            { type: 'conditionType2' },
        ];

        const result = ruleConditionService.isRuleRestricted(conditions, 'assignmentOne');
        expect(result).toBeFalsy();
    });

    it('should not be restricted if group parameter is not set', async () => {
        const ruleConditionService = new RuleConditionService();

        const result = ruleConditionService.isRuleRestricted([], undefined);
        expect(result).toBeFalsy();
    });

    it('should have the correct operators for date', async () => {
        const ruleConditionService = new RuleConditionService();

        const expected = [
            {
                identifier: '=',
                label: 'global.sw-condition.operator.equals',
            },
            {
                identifier: '>',
                label: 'global.sw-condition.operator.greaterThan',
            },
            {
                identifier: '>=',
                label: 'global.sw-condition.operator.greaterThanEquals',
            },
            {
                identifier: '<',
                label: 'global.sw-condition.operator.lowerThan',
            },
            {
                identifier: '<=',
                label: 'global.sw-condition.operator.lowerThanEquals',
            },
            {
                identifier: '!=',
                label: 'global.sw-condition.operator.notEquals',
            },
        ];

        const operators = ruleConditionService.getOperatorSet('date');

        expect(operators).toEqual(expected);
    });

    it('should get the restrictedConditions', () => {
        const ruleConditionService = new RuleConditionService();

        ruleConditionService.addAwarenessConfiguration('flowTrigger.someFlow', {
            notEquals: ['cartCartAmount'],
            snippet: 'someFlowSnippet',
        });

        const rule = {
            id: 'random-id',
            flowSequences: [
                {
                    flow: { eventName: 'someFlow' },
                },
                {
                    flow: { eventName: 'anotherFlow' },
                },
            ],
        };
        const result = ruleConditionService.getRestrictedConditions(rule);

        expect(result).toStrictEqual({
            cartCartAmount: [
                {
                    associationName: 'flowTrigger.someFlow',
                    snippet: 'someFlowSnippet',
                },
            ],
        });
    });

    it('should get the keys of awareness configurations that have an equalsAny config', () => {
        const ruleConditionService = new RuleConditionService();

        ruleConditionService.addAwarenessConfiguration('personaPromotions', {
            equalsAny: [
                'cartCartAmount',
                'cartLineItemsCount',
            ],
            snippet: 'someFlowSnippet',
        });

        const result = ruleConditionService.getAwarenessKeysWithEqualsAnyConfig();

        expect(result).toStrictEqual(['personaPromotions']);
    });
});
