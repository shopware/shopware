import ApiService from '../api.service';

/**
 * @private
 * @package services-settings
 */
export default class RuleConditionsConfigApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, null, 'application/json');
        this.name = 'ruleConditionsConfigApiService';
    }

    load() {
        if (Shopware.Store.get('ruleConditionsConfig').config !== null) {
            return Promise.resolve();
        }

        return this.httpClient
            .get('_info/rule-config', {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                Shopware.Store.get('ruleConditionsConfig').config = ApiService.handleResponse(response);
            });
    }
}
