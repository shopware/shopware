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

    async createDocument(orderId,
        documentTypeName,
        documentConfig = {},
        referencedDocumentId = null,
        additionalParams = {},
        additionalHeaders = {},
        file = null) {
        const route = `/_action/order/${orderId}/document/${documentTypeName}`;

        const params = {
            config: documentConfig,
            referenced_document_id: referencedDocumentId
        };

        if (file) {
            params.static = true;
        }

        const createResponse = await this.httpClient.post(route, params, {
            additionalParams,
            headers: this.getBasicHeaders(additionalHeaders)
        });

        if (file && createResponse.data.documentId) {
            const fileName = file.name.split('.').shift();
            const fileExtension = file.name.split('.').pop();
            // eslint-disable-next-line max-len
            const uploadRoute = `/_action/document/${createResponse.data.documentId}/upload?fileName=${documentConfig.documentNumber}_${fileName}&extension=${fileExtension}`;

            const uploadResponse = await this.httpClient.post(uploadRoute, file, {
                additionalParams,
                headers: {
                    ...this.getBasicHeaders(additionalHeaders),
                    'Content-Type': file.type
                }
            });

            return uploadResponse;
        }

        return createResponse;
    }

    generateDocumentPreviewLink(orderId, orderDeepLink, documentTypeName, config, context) {
        // eslint-disable-next-line max-len
        return `${context.apiPath}/v${this.getApiVersion()}/_action/order/${orderId}/${orderDeepLink}/document/${documentTypeName}/preview?config=${config}`;
    }

    generateDocumentLink(documentId, documentDeepLink, context, download = false) {
        // eslint-disable-next-line max-len
        return `${context.apiPath}/v${this.getApiVersion()}/_action/document/${documentId}/${documentDeepLink}${download ? '?download=1' : ''}`;
    }
}

export default DocumentApiService;
