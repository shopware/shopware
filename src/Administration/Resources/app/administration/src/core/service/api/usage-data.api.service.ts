import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

type UsageDataContext = {
    isConsentGiven: boolean;
    isBannerHidden: boolean;
};

/**
 * Gateway for the API endpoint "metrics"
 *
 * @private
 *
 * @package data-services
 */
export default class UsageDataApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'usage-data') {
        super(httpClient, loginService, apiEndpoint, 'application/json');

        this.name = 'usageDataService';
    }

    public async getConsent(): Promise<UsageDataContext> {
        const headers = this.getBasicHeaders();
        const params = {};

        const { data } = await this.httpClient.get<UsageDataContext>(`/${this.getApiBasePath()}/consent`, {
            params,
            headers,
        });

        return data;
    }

    public async acceptConsent(): Promise<void> {
        await this.httpClient.post<boolean>(`/${this.getApiBasePath()}/accept-consent`, null, {
            headers: this.getBasicHeaders(),
        });
    }

    public async revokeConsent(): Promise<void> {
        await this.httpClient.post<boolean>(`/${this.getApiBasePath()}/revoke-consent`, null, {
            headers: this.getBasicHeaders(),
        });
    }

    public async hideBanner(): Promise<void> {
        await this.httpClient.post<void>(`/${this.getApiBasePath()}/hide-consent-banner`, null, {
            headers: this.getBasicHeaders(),
        });
    }
}

/**
 * @private
 * @package data-services
 */
export type { UsageDataApiService, UsageDataContext };
