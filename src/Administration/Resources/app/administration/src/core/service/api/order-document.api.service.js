import ApiService from '../api.service';

/**
 * Gateway for the API end point "order/document"
 * @class
 * @extends ApiService
 */
export default class OrderDocumentApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order/document') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'orderDocumentApiService';
    }

    create(payload, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient.post(`/_admin/${this.apiEndpoint}/create`, payload, {
            additionalParams,
            headers: this.getBasicHeaders(additionalHeaders),
        });
    }
}
