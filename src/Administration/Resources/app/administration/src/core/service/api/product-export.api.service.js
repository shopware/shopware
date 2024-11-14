import ApiService from '../api.service';

/**
 * Gateway for the API end point "product-export"
 * @class
 * @extends ApiService
 */
class ProductExportApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'product-export') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'productExportService';
    }

    validateProductExportTemplate(productExport) {
        const apiRoute = `/_action/${this.getApiBasePath()}/validate`;

        return this.httpClient
            .post(apiRoute, productExport, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    previewProductExport(productExport) {
        const apiRoute = `/_action/${this.getApiBasePath()}/preview`;

        return this.httpClient
            .post(apiRoute, productExport, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Get the generated access key and secret access key from the API
     *
     * @param {Object} additionalParams
     * @param {Object} additionalHeaders
     * @returns {Promise<T>}
     */
    generateKey(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get('/_action/access-key/product-export', {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ProductExportApiService;
