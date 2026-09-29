/**
 * @sw-package framework:fundamentals
 */
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from 'src/core/service/login.service';
import ApiService from 'src/core/service/api.service';
import type { ConsentDTO } from './consent.store';

/**
 * @private
 */
export default class ConsentApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService) {
        super(httpClient, loginService, 'consents', 'application/json');
    }

    list() {
        return this.httpClient.get<Record<string, ConsentDTO>>(this.getApiBasePath(), {
            headers: this.getBasicHeaders(),
        });
    }

    accept(consent: string, revision?: string | null) {
        return this.httpClient.post<ConsentDTO>(
            `${this.getApiBasePath()}/accept`,
            {
                consent,
                revision,
            },
            {
                headers: this.getBasicHeaders(),
            },
        );
    }

    revoke(consent: string) {
        return this.httpClient.post<ConsentDTO>(
            `${this.getApiBasePath()}/revoke`,
            {
                consent,
            },
            {
                headers: this.getBasicHeaders(),
            },
        );
    }
}
