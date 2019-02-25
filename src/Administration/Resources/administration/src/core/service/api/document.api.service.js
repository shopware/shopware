import ApiService from '../api.service';

/**
 * Gateway for the API end point "document"
 * @class
 * @extends ApiService
 */
class DocumentApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'document') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'documentService';
    }

    createDocument(orderId, documentType, documentConfig = {}, additionalParams = {}, additionalHeaders = {}) {
        const route = `/_action/order/${orderId}/document/${documentType}`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(route, { config: documentConfig }, {
                additionalParams,
                headers
            });
    }

    downloadDocument(documentId, salesChannelId, additionalHeaders = {}) {
        const path = `v1/document/${documentId}`;
        const route = `/_proxy/storefront-api/${salesChannelId}/${path}`;
        const headers = this.getBasicHeaders(additionalHeaders);

        console.log('headers', headers);

        return this.httpClient
            .get(route, {
                responseType: 'blob',
                headers
            });
    }
}

export default DocumentApiService;
