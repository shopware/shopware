import type { AxiosInstance, AxiosResponse } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class CountryApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService) {
        super(httpClient, loginService, 'country', 'application/json');

        this.name = 'countryApiService';
    }

    defaultCountryAddressFormat(): Promise<unknown> {
        return this.httpClient
            .get(`/_info/${this.getApiBasePath()}/address/default-format`, {
                headers: this.getBasicHeaders(),
            }).then((response: AxiosResponse<unknown>) => {
                return ApiService.handleResponse(response);
            });
    }
}
