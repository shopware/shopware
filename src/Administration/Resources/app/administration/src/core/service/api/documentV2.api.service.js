import ApiService from '../api.service';
import { DocumentEvents } from './document.api.service';

/**
 * @sw-package after-sales
 * @class
 * @extends ApiService
 */
class DocumentV2ApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'document-v2') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'documentV2Service';
        this.$listener = () => {};
    }

    getAvailableTypes() {
        return this.httpClient.get('/_action/order/document-v2/available-types', {
            headers: this.getBasicHeaders(),
        });
    }

    createDocument(
        orderId,
        orderVersionId,
        documentTypeName,
        formats,
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
                    formats,
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

    uploadDocument(
        orderId,
        orderVersionId,
        documentTypeName,
        format,
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
            format,
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

    previewDocument(
        orderId,
        orderVersionId,
        documentTypeName,
        format,
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
                    format,
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
                if (error.response?.data?.errors) {
                    this.$listener(
                        this.createDocumentEvent(DocumentEvents.DOCUMENT_FAILED, error.response.data.errors.pop()),
                    );
                }
            });
    }

    getDocument(documentId, deepLinkCode, format = 'pdf') {
        return this.httpClient.get(`/_action/order/document-v2/${documentId}/${deepLinkCode}/download/${format}`, {
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
export default DocumentV2ApiService;
