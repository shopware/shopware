import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';

import ApiService from '../api.service';

/**
 * @class
 * @internal
 * @extends ApiService
 * @sw-package framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class SsoSettingsService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'api') {
        super(httpClient, loginService, apiEndpoint, 'application/json');
        this.name = 'ssoSettingsService';
    }

    isSso() {
        return this.httpClient
            .get<{ isSso: boolean }>('/_info/is-sso', {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}
