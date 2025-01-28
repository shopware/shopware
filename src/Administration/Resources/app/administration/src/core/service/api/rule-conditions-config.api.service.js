import ApiService from '../api.service';

/**
 * @private
 * @sw-package fundamentals@after-sales
 */
export default class RuleConditionsConfigApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, null, 'application/json');
        this.name = 'ruleConditionsConfigApiService';
    }

    load() {
        if (Shopware.State.getters['ruleConditionsConfig/getConfig']() !== null) {
            return Promise.resolve();
        }

        return this.httpClient
            .get('_info/rule-config', {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                Shopware.State.commit('ruleConditionsConfig/setConfig', ApiService.handleResponse(response));
            });
    }
}
