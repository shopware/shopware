import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * Custom gateway for validation routes
 *
 * @class
 * @extends ApiService
 * @sw-package fundamentals@framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class ValidationApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'validate') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'validationApiService';
    }

    validateEmailAddress(email: string) {
        const apiRoute = `/_action/${this.getApiBasePath()}/email`;

        return this.httpClient
            .post(
                apiRoute,
                { email: email },
                { params: {}, headers: this.getBasicHeaders() },
            )
            .catch(() => {
                return Promise.resolve(false);
            })
            .then(() => {
                return Promise.resolve(true);
            });
    }
}
