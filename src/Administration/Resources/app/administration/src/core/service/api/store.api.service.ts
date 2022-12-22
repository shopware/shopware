import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

interface StoreParams {
    language: string|null,
    [key: string]: unknown,
}

interface UserInfo {
    avatarUrl: string,
    email: string,
    name: string,
}

interface UserInfoResponse {
    userInfo: UserInfo|null,
}

/**
 * @package merchant-services
 *
 * Gateway for the API end point "store"
 * @deprecated tag:v6.5.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class StoreApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'store') {
        super(httpClient, loginService, apiEndpoint, 'application/json');

        this.name = 'storeService';
    }

    /**
     * @deprecated tag:v6.5.0 - will be removed withouth replacement
     */
    public async ping() {
        await this.httpClient.get(
            `/_action/${this.getApiBasePath()}/ping`,
            { headers: this.getBasicHeaders() },
        );
    }

    public async login(shopwareId: string, password: string) {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        await this.httpClient.post(
            `/_action/${this.getApiBasePath()}/login`,
            { shopwareId, password },
            { params, headers },
        );
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

        await this.httpClient.post(
            `/_action/${this.getApiBasePath()}/logout`,
            {},
            { params, headers },
        );
    }

    /**
     * @deprecated tag:v6.5.0 Unused method will be removed
     */
    public async getLicenseList(): Promise<unknown> {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        return ApiService.handleResponse(await this.httpClient.get(
            `/_action/${this.getApiBasePath()}/licenses`,
            { params, headers },
        ));
    }

    public async getUpdateList() {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        return ApiService.handleResponse(await this.httpClient.get(
            `/_action/${this.getApiBasePath()}/updates`,
            { params, headers },
        ));
    }

    /**
     * @deprecated tag:v6.5.0 - Use ExtensionStoreActionService.downloadExtension() instead
     */
    public async downloadPlugin(pluginName: string, unauthenticated = false, onlyDownload = false) {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams({
            pluginName,
            unauthenticated,
        });

        const downloadResponse = await this.httpClient.get(
            `/_action/${this.getApiBasePath()}/download`,
            { params, headers },
        );

        if (onlyDownload) {
            return ApiService.handleResponse(downloadResponse);
        }

        return ApiService.handleResponse(await this.httpClient.post(
            '/_action/plugin/update',
            null,
            { params, headers },
        ));
    }

    /**
     * @deprecated tag:v6.5.0 - Use ExtensionStoreActionService.downloadExtension() and
     * ExtensionStoreActionService.updateExtension() instead
     */
    public async downloadAndUpdatePlugin(pluginName: string, unauthenticated = false) {
        return this.downloadPlugin(pluginName, unauthenticated, true);
    }

    public async getLicenseViolationList() {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        return ApiService.handleResponse(await this.httpClient.post(
            `/_action/${this.getApiBasePath()}/plugin/search`,
            null,
            { params, headers },
        ));
    }

    /**
     * @deprecated tag:v6.5.0 - will be private in future versions
     */
    public getBasicParams(additionalParams = {}): StoreParams {
        const basicParams = {
            language: localStorage.getItem('sw-admin-locale'),
        };

        return Object.assign({}, basicParams, additionalParams);
    }
}

/**
 * @package merchant-services
 * @deprecated tag:v6.5.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { StoreApiService, UserInfo };
