import ApiService from 'src/core/service/api.service';

/**
 * @private
 * @sw-package inventory
 */
export default class ProductTypeApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/product/types') {
        super(httpClient, loginService, apiEndpoint);
    }

    async fetchProductTypes() {
        const response = await this.httpClient.get(
            `/${this.apiEndpoint}`,
            {
                headers: this.getBasicHeaders(),
            },
        );

        const result = await ApiService.handleResponse(response);

        if (!Array.isArray(result)) {
            return [];
        }

        return result;
    }
}
