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

    generateDocumentPreviewLink(orderId, orderDeepLink, documentTypeId, config) {
        return `/api/v1/_action/order/${orderId}/${orderDeepLink}/document/${documentTypeId}/preview?config=${config}`;
    }

    generateDocumentLink(documentId, documentDeepLink, download = false) {
        return `/api/v1/_action/document/${documentId}/${documentDeepLink}${download ? '?download=1' : ''}`;
    }
}

export default DocumentApiService;
