/**
 * @package services-settings
 * @group disabledCompat
 */
import RuleConditionsConfigApiService from 'src/core/service/api/rule-conditions-config.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';
import ruleConditionsConfig from './../../../app/component/rule/condition-type/_mocks/ruleConditionsConfig.json';

function getRuleConditionsConfigApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);

    const ruleConditionsConfigApiService = new RuleConditionsConfigApiService(client, loginService);
    return { ruleConditionsConfigApiService, clientMock };
}

describe('ruleConditionsConfigApiService', () => {
    it('is registered correctly', async () => {
        const { ruleConditionsConfigApiService } = getRuleConditionsConfigApiService();
        expect(ruleConditionsConfigApiService).toBeInstanceOf(RuleConditionsConfigApiService);
    });

    it('is request send correctly', async () => {
        const { ruleConditionsConfigApiService, clientMock } = getRuleConditionsConfigApiService();

        clientMock.onGet('/_info/rule-config')
            .reply(
                200,
                ruleConditionsConfig,
            );

        await ruleConditionsConfigApiService.load();

        expect(Shopware.State.getters['ruleConditionsConfig/getConfig']()).toEqual(ruleConditionsConfig);
    });

    it('is request prevented if store has config', async () => {
        const { ruleConditionsConfigApiService } = getRuleConditionsConfigApiService();

        Shopware.State.commit('ruleConditionsConfig/setConfig', { foo: 'bar' });

        await ruleConditionsConfigApiService.load();

        expect(Shopware.State.getters['ruleConditionsConfig/getConfig']()).toEqual({ foo: 'bar' });
    });
});
