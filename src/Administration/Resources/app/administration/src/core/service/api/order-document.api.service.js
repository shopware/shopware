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

    create(documentType, payload, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient.post(`/_action/${this.apiEndpoint}/${documentType}/create`, payload, {
            additionalParams,
            headers: this.getBasicHeaders(additionalHeaders),
        });
    }

    download(documentIds, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient.post(`/_action/${this.apiEndpoint}/download`, { documentIds }, {
            additionalParams,
            responseType: 'blob',
            headers: this.getBasicHeaders(additionalHeaders),
        });
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed
     */
    extendingDeprecatedService(additionalHeaders = {}) {
        return this.httpClient.get('/_action/document/extending-deprecated-service', {
            headers: this.getBasicHeaders(additionalHeaders),
        });
    }
}
