/**
 * @sw-package framework:fundamentals
 */
import type { AxiosInstance } from 'axios';
import type { LoginService } from 'src/core/service/login.service';
import ApiService from 'src/core/service/api.service';
import type { ConsentDTO } from './consent.store';

/**
 * @private
 */
export default class ConsentApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService) {
        super(httpClient, loginService, 'consents', 'application/json');
    }

    list() {
        return this.httpClient.get<Record<string, ConsentDTO>>(
            this.getApiBasePath(), {
                headers: this.getBasicHeaders(),
            },
        );
    };

    accept(name: string): Promise<void> {
        return this.httpClient.post(
            `${this.getApiBasePath()}/${name}/accept`,
            {},
            {
                headers: this.getBasicHeaders(),
            },
        )
    }

    revoke(name: string): Promise<void> {
        return this.httpClient.post(
            `${this.getApiBasePath()}/${name}/revoke`,
            {},
            {
                headers: this.getBasicHeaders(),
            },
        )
    }
}
