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
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'validation') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'validationApiService';
    }

    async validateEmailAddress(email: string) {
        const apiRoute = `/${this.getApiBasePath('email', '_action')}`;
        if (!/.+@.+\..+/.test(email)) {
            return Promise.resolve(false);
        }

        return this.httpClient
            .post(apiRoute, { email: email }, { params: {}, headers: this.getBasicHeaders() })
            .then((resp) => {
                return resp.status === 204;
            })
            .catch(() => {
                return false;
            });
    }
}
