/**
 * @private
 * @sw-package inventory
 */
import type { AxiosInstance } from 'axios';

import ApiService from 'src/core/service/api.service';
import type { ApiResponse } from 'src/core/service/api.service';
import type { LoginService } from 'src/core/service/login.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class ProductTypeApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint: string = '_action/product/types') {
        super(httpClient, loginService, apiEndpoint);

        this.name = 'productTypeApiService';
    }

    async fetchProductTypes(): Promise<unknown> {
        const response = await this.httpClient.get(`/${this.apiEndpoint}`, {
            headers: this.getBasicHeaders(),
        });

        const result: ApiResponse<unknown> = await ApiService.handleResponse(response);

        if (!Array.isArray(result)) {
            return Promise.resolve([]);
        }

        return result;
    }
}
