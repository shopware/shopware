import ApiService from '../api.service';

/**
 * @class
 * @internal
 * @extends ApiService
 * @sw-package after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class SaasSettingsService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'api') {
        super(httpClient, loginService, apiEndpoint, 'application/json');
        this.name = 'saasSettingsService';
    }

    isSaas() {
        return this.httpClient.get(
            '/_info/is-saas',
            {
                headers: this.getBasicHeaders(),
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

