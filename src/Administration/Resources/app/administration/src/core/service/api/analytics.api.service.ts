import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

type AnalyticsToken = {
    token: string;
    expiresAt: number;
};

export default class AnalyticsApiService extends ApiService {
    token: AnalyticsToken | null = null;

    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'analytics') {
        super(httpClient, loginService, apiEndpoint, 'application/json');

        this.name = 'analyticsService';
    }

    public async getToken(): Promise<AnalyticsToken> {
        if (this.token !== null) {
            if (!this.isTokenValid(this.token.expiresAt)) {
                this.token = await this.fetchToken();
            }

            return this.token;
        }

        this.token = await this.fetchToken();

        return this.token;
    }

    private async fetchToken() : Promise<AnalyticsToken>
    {
        const headers = this.getBasicHeaders();
        const params = {};

        const { data } = await this.httpClient.get<AnalyticsToken>(`/${this.getApiBasePath()}/token`, {
            params,
            headers,
        });

        return data;
    }

    private isTokenValid(expiryTimestamp: number)
    {
        const currentTime = Math.floor(Date.now() / 1000);
        return currentTime < expiryTimestamp;
    }
}

/**
 * @private
 * @sw-package data-services
 */
export type { AnalyticsApiService, AnalyticsToken };
