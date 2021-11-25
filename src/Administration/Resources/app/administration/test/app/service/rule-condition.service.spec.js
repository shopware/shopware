import createConditionService from 'src/app/service/rule-condition.service';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search-ids/rule',
    status: 200,
    response: {
        data: ['restricted-rule-id']
    }
});

describe('src/app/service/rule-condition.service.js', () => {
    it('should be a function', () => {
        expect(typeof createConditionService).toEqual('function');
    });

    it('should return restricted rules', async () => {
        const ruleConditionService = createConditionService();
        ruleConditionService.addAwarenessConfiguration('personaPromotions', {
            notEquals: ['cartCartAmount']
        });

        return ruleConditionService.getRestrictedRules('personaPromotions').then(result => {
            expect(result).toEqual(['restricted-rule-id']);
        });
    });

    it('should return empty array when the specified relation does not exist', () => {
        const ruleConditionService = createConditionService();

        return ruleConditionService.getRestrictedRules('personaPromotions').then(result => {
            expect(result).toEqual([]);
        });
    });

    it('should return restricted conditions', () => {
        const ruleConditionService = createConditionService();
        ruleConditionService.addAwarenessConfiguration('personaPromotions', {
            notEquals: ['cartCartAmount'],
            snippet: 'random-snippi'
        });

        const rule = {
            personaPromotions: []
        };

        const restrictedConditions = ruleConditionService.getRestrictedConditions(rule);

        expect(restrictedConditions).toEqual({
            cartCartAmount: {
                snippet: 'random-snippi'
            }
        });
    });

    it('should add config item', () => {
        const ruleConditionService = createConditionService();

        const configItemBefore = ruleConditionService.getAwarenessConfigurationByAssignmentName('personaPromotions');

        expect(configItemBefore).toEqual({});

        ruleConditionService.addAwarenessConfiguration('personaPromotions', {
            notEquals: ['cartCartAmount']
        });

        const configItemAfter = ruleConditionService.getAwarenessConfigurationByAssignmentName('personaPromotions');

        expect(configItemAfter).toEqual({
            notEquals: ['cartCartAmount']
        });
    });

    it('should get config item', () => {
        const ruleConditionService = createConditionService();
        ruleConditionService.addAwarenessConfiguration('personaPromotions', {
            notEquals: ['cartCartAmount']
        });

        const configItem = ruleConditionService.getAwarenessConfigurationByAssignmentName('personaPromotions');

        expect(configItem).toEqual({
            notEquals: ['cartCartAmount']
        });
    });
});
