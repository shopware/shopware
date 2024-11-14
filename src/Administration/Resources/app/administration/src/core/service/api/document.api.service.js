import ApiService from '../api.service';

const DocumentEvents = {
    DOCUMENT_FAILED: 'create-document-fail',
    DOCUMENT_FINISHED: 'create-document-finished',
};

/**
 * @package checkout
 * Gateway for the API end point "document"
 * @class
 * @extends ApiService
 */
class DocumentApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'document') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'documentService';
        this.$listener = () => ({});
    }

    createDocument(
        orderId,
        documentTypeName,
        config = {},
        referencedDocumentId = null,
        additionalParams = {},
        additionalHeaders = {},
        file = null,
    ) {
        let route = `_action/order/document/${documentTypeName}/create`;
        const headers = this.getBasicHeaders(additionalHeaders);

        const params = {
            orderId,
            config,
            referencedDocumentId,
        };

        if (file || config.documentMediaFileId) {
            params.static = true;
        }

        let responseDoc;
        return this.httpClient
            .post(route, [params], {
                additionalParams,
                headers,
            })
            .then((response) => {
                responseDoc = response.data?.data;

                if (file && file instanceof File && responseDoc && responseDoc[0]?.documentId) {
                    const documentId = responseDoc[0]?.documentId;
                    const fileName = file.name.split('.').shift();
                    const fileExtension = file.name.split('.').pop();
                    // eslint-disable-next-line max-len
                    route = `/_action/document/${documentId}/upload?fileName=${config.documentNumber}_${fileName}&extension=${fileExtension}`;
                    headers['Content-Type'] = file.type;
                    responseDoc = this.httpClient.post(route, file, {
                        additionalParams,
                        headers,
                    });
                }

                const errors = response.data?.errors;

                if (errors && errors.hasOwnProperty(orderId)) {
                    this.$listener(this.createDocumentEvent(DocumentEvents.DOCUMENT_FAILED, errors[orderId].pop()));

                    return;
                }

                this.$listener(this.createDocumentEvent(DocumentEvents.DOCUMENT_FINISHED));
                // eslint-disable-next-line consistent-return
                return Promise.resolve(responseDoc);
            })
            .catch((error) => {
                if (error.response?.data?.errors) {
                    this.$listener(
                        this.createDocumentEvent(DocumentEvents.DOCUMENT_FAILED, error.response.data.errors.pop()),
                    );
                }
            });
    }

    getDocumentPreview(orderId, orderDeepLink, documentTypeName, params) {
        const config = JSON.stringify(params);

        return this.httpClient
            .get(`/_action/order/${orderId}/${orderDeepLink}/document/${documentTypeName}/preview`, {
                params: { config },
                responseType: 'blob',
                headers: this.getBasicHeaders(),
            })
            .catch(async (error) => {
                const errorObject = JSON.parse(await error.response.data.text());
                if (errorObject.errors) {
                    this.$listener(this.createDocumentEvent('create-document-fail', errorObject.errors.pop()));
                }
            });
    }

    getDocument(documentId, documentDeepLink, context, download = false) {
        return this.httpClient.get(`/_action/document/${documentId}/${documentDeepLink}${download ? '?download=1' : ''}`, {
            responseType: 'blob',
            headers: this.getBasicHeaders(),
        });
    }

    createDocumentEvent(action, payload) {
        return { action, payload };
    }

    setListener(callback) {
        this.$listener = callback;
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export { DocumentApiService as default, DocumentEvents };
