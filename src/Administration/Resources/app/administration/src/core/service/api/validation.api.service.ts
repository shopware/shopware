import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

interface Email {
    email: string;
}

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

    validateEmailAddress(email: string) {
        const apiRoute = `/${this.getApiBasePath()}/email`;

        return this.httpClient
            .post(
                apiRoute,
                { email: email },
                { params: {}, headers: this.getBasicHeaders() },
            )
            .catch(() => {
                return Promise.resolve(false);
            })
            .then((response) => {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                return response.data.isValid;
            });
    }

    validateEmailAddresses(emails: Array<Email>) {
        const apiRoute = `/${this.getApiBasePath()}/emails`;

        return this.httpClient
            .post(
                apiRoute,
                { emails: JSON.stringify(emails) },
                { params: {}, headers: this.getBasicHeaders() },
            )
            .catch(() => {
                return Promise.resolve(false);
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}
