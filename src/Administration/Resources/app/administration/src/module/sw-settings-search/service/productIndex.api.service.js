const ApiService = Shopware.Classes.ApiService;

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
