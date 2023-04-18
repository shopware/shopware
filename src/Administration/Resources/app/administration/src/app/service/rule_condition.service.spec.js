import RuleConditionService from 'src/app/service/rule-condition.service';

describe('app/service/rule-condition.service.js', () => {
    const service = new RuleConditionService();


    it('should have the correct operators for date', async () => {
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

        const operators = service.getOperatorSet('date');

        expect(operators).toEqual(expected);
    });
});
