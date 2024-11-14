import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

interface StoreParams {
    language: string | null;
    [key: string]: unknown;
}

interface UserInfo {
    avatarUrl: string;
    email: string;
    name: string;
}

interface UserInfoResponse {
    userInfo: UserInfo | null;
}

/**
 * @private
 * @package services-settings
 * Gateway for the API end point "store"
 */
export default class StoreApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'store') {
        super(httpClient, loginService, apiEndpoint, 'application/json');

        this.name = 'storeService';
    }

    public async login(shopwareId: string, password: string) {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        await this.httpClient.post(`/_action/${this.getApiBasePath()}/login`, { shopwareId, password }, { params, headers });
    }

    public async checkLogin() {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        const { data } = await this.httpClient.post<UserInfoResponse>(
            `/_action/${this.getApiBasePath()}/checklogin`,
            {},
            { params, headers },
        );

        return data;
    }

    public async logout() {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        await this.httpClient.post(`/_action/${this.getApiBasePath()}/logout`, {}, { params, headers });
    }

    public async getUpdateList(): Promise<unknown> {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        return ApiService.handleResponse(
            await this.httpClient.get<unknown>(`/_action/${this.getApiBasePath()}/updates`, { params, headers }),
        );
    }

    public async getLicenseViolationList(): Promise<unknown> {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        return ApiService.handleResponse(
            await this.httpClient.post<unknown>(`/_action/${this.getApiBasePath()}/plugin/search`, null, {
                params,
                headers,
            }),
        );
    }

    private getBasicParams(additionalParams = {}): StoreParams {
        const basicParams = {
            language: localStorage.getItem('sw-admin-locale'),
        };

        return { ...basicParams, ...additionalParams };
    }
}

/**
 * @private
 * @package services-settings
 */
export type { StoreApiService, UserInfo };
