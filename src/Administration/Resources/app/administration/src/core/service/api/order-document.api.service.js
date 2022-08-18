import ApiService from '../api.service';

/**
 * Gateway for the API end point "order/document"
 * @class
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal - Only to be used by the bulk edit order document
 * @extends ApiService
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class OrderDocumentApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order/document') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'orderDocumentApiService';
    }

    /**
     * @deprecated tag:v6.5.0 - create method will be removed, use generate method instead
     */
    create(payload, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient.post(`/_admin/${this.apiEndpoint}/create`, payload, {
            additionalParams,
            headers: this.getBasicHeaders(additionalHeaders),
        });
    }

    generate(documentType, payload, additionalParams = {}, additionalHeaders = {}) {
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
