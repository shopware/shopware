import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * Gateway for the API end point 'user-config'
 * @sw-package fundamentals@framework
 * @private
 */
export default class UserConfigService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = '_info/config-me') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'userConfigService';
    }

    /**
     * @description Process search user-config based on provide array keys of user-config,
     * if keys is null, get all config of current logged-in user
     */
    search(keys: string[] | null = null) {
        const headers = this.getBasicHeaders();
        const params = { keys };

        return this.httpClient
            .get<{ data: Record<string, unknown[]> }>(this.getApiBasePath(), {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            })
            .catch((error) => {
                Shopware.Utils.debug.error('UserConfigService', error);
            });
    }

    /**
     * @description Process mass upsert user-config for current logged-in user
     */
    upsert(upsertData: Record<string, unknown[]>): Promise<void> {
        const headers = this.getBasicHeaders();

        return this.httpClient.post<void>(this.getApiBasePath(), upsertData, { headers }).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}
