import ApiService from '../api.service';

/**
 * @class
 * @internal
 * @extends ApiService
 * @sw-package framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class SsoSettingsService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'api') {
        super(httpClient, loginService, apiEndpoint, 'application/json');
        this.name = 'ssoSettingsService';
    }

    isSso() {
        return this.httpClient
            .get('/_info/is-sso', {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}
