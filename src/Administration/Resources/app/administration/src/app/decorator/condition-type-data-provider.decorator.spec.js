import 'src/app/decorator/condition-type-data-provider.decorator';
import createConditionService from 'src/app/service/rule-condition.service';

describe('entity-collection.data.ts', () => {
    beforeAll(async () => {
        Shopware.Service().register('ruleConditionDataProviderService', () => {
            return createConditionService();
        });
    });

    it('should register conditions with correct scope', async () => {
        const condition = Shopware.Service('ruleConditionDataProviderService').getByType('language');

        expect(condition).not.toBeUndefined();
        expect(condition.scopes).toEqual(['global']);
    });

    it('should add app script conditions', async () => {
        Shopware.Service('ruleConditionDataProviderService').addScriptConditions([
            {
                id: 'bar',
                name: 'foo',
                group: 'misc',
                config: {},
            }
        ]);

        const condition = Shopware.Service('ruleConditionDataProviderService').getByType('bar');

        expect(condition.component).toEqual('sw-condition-script');
        expect(condition.type).toEqual('scriptRule');
        expect(condition.label).toEqual('foo');
    });
});
