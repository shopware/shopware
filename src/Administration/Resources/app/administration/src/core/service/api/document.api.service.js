import ApiService from '../api.service';

const DocumentEvents = {
    DOCUMENT_FAILED: 'create-document-fail',
    DOCUMENT_FINISHED: 'create-document-finished',
};

/**
 * @sw-package checkout
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

    getDocumentPreview(orderId, orderDeepLink, documentTypeName, params, additionalParams = {}) {
        const config = JSON.stringify(params);

        return this.httpClient
            .get(`/_action/order/${orderId}/${orderDeepLink}/document/${documentTypeName}/preview`, {
                params: { config, ...additionalParams },
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

    getDocumentV2AvailableTypes() {
        return this.httpClient.get('/_action/order/document-v2/available-types', {
            headers: this.getBasicHeaders(),
        });
    }

    createDocumentV2(
        orderId,
        orderVersionId,
        documentTypeName,
        fileTypes,
        documentNumber,
        documentDate,
        documentComment = '',
        additionalHeaders = {},
    ) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(
                '/_action/order/document-v2/create',
                {
                    orderId,
                    orderVersionId,
                    documentType: documentTypeName,
                    fileTypes,
                    documentNumber,
                    documentDate,
                    documentComment,
                },
                {
                    headers,
                },
            )
            .then((response) => {
                this.$listener(this.createDocumentEvent(DocumentEvents.DOCUMENT_FINISHED));

                return Promise.resolve(response);
            })
            .catch((error) => {
                if (error.response?.data?.errors) {
                    this.$listener(
                        this.createDocumentEvent(DocumentEvents.DOCUMENT_FAILED, error.response.data.errors.pop()),
                    );
                }
            });
    }

    uploadDocumentV2(
        orderId,
        orderVersionId,
        documentTypeName,
        fileType,
        documentNumber,
        documentDate,
        documentComment = '',
        mediaId = null,
        file = null,
        referencedDocumentId = null,
        additionalHeaders = {},
    ) {
        const headers = this.getBasicHeaders(additionalHeaders);
        const payload = {
            orderId,
            orderVersionId,
            documentType: documentTypeName,
            fileType,
            documentNumber,
            documentDate,
            documentComment,
            mediaId,
            referencedDocumentId,
        };

        let request;

        if (typeof File !== 'undefined' && file instanceof File) {
            headers['Content-Type'] = file.type;

            request = this.httpClient.post('/_action/order/document-v2/upload', file, {
                params: {
                    ...payload,
                    extension: file.name.split('.').pop(),
                    fileName: file.name.split('.').shift(),
                },
                headers,
            });
        } else {
            request = this.httpClient.post('/_action/order/document-v2/upload', payload, {
                headers,
            });
        }

        return request
            .then((response) => {
                this.$listener(this.createDocumentEvent(DocumentEvents.DOCUMENT_FINISHED));

                return Promise.resolve(response);
            })
            .catch((error) => {
                if (error.response?.data?.errors) {
                    this.$listener(
                        this.createDocumentEvent(DocumentEvents.DOCUMENT_FAILED, error.response.data.errors.pop()),
                    );
                }
            });
    }

    getDocumentPreviewV2(
        orderId,
        orderVersionId,
        documentTypeName,
        fileType,
        documentNumber,
        documentDate,
        documentComment = '',
        additionalHeaders = {},
    ) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(
                '/_action/order/document-v2/preview',
                {
                    orderId,
                    orderVersionId,
                    documentType: documentTypeName,
                    fileType,
                    documentNumber,
                    documentDate,
                    documentComment,
                },
                {
                    responseType: 'blob',
                    headers,
                },
            )
            .catch(async (error) => {
                const errorObject = JSON.parse(await error.response.data.text());
                if (errorObject.errors) {
                    this.$listener(this.createDocumentEvent(DocumentEvents.DOCUMENT_FAILED, errorObject.errors.pop()));
                }
            });
    }

    getDocument(documentId, documentDeepLink, context, download = false, fileType = 'pdf') {
        return this.httpClient.get(
            `/_action/document/${documentId}/${documentDeepLink}?fileType=${fileType}${download ? '&download=1' : ''}`,
            {
                responseType: 'blob',
                headers: this.getBasicHeaders(),
            },
        );
    }

    getDocumentV2(documentId, documentDeepLink, fileType = 'pdf') {
        return this.httpClient.get(`/_action/order/document-v2/${documentId}/${documentDeepLink}/download/${fileType}`, {
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
