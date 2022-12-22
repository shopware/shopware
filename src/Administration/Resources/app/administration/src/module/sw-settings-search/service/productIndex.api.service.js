/**
 * @package system-settings
 */
const ApiService = Shopware.Classes.ApiService;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class ProductIndexService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'product.indexer') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'liveSearchService';
    }

    index(offset) {
        const route = '/_action/indexing/product.indexer';
        const headers = this.getHeaders();
        return this.httpClient.post(route, { offset: offset }, { headers });
    }

    getHeaders() {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }
}
