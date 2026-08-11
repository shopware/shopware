import type { AxiosError, AxiosInstance, AxiosResponse } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';
import { DocumentEvents } from './document.api.service';
import { DOCUMENT_TYPES } from '../../../module/sw-order/service/documentV2.service';
import fileReaderUtils from 'src/core/service/utils/file-reader.utils';

type DocumentTypeFormats = {
    formats: string[];
};

type AvailableDocumentTypesResponse = {
    documentTypes?: Record<string, DocumentTypeFormats>;
};

type DocumentCreateResponse = {
    documentId: string;
    deepLinkCode: string;
    formats: string[];
};

type DocumentError = {
    code: string;
    detail: string;
    [key: string]: unknown;
};

type DocumentEvent = {
    action: string;
    payload?: DocumentError;
};

type DocumentListener = (event: DocumentEvent) => void;

type DocumentRequestPayload = {
    orderId: string;
    documentType: string;
    documentNumber: string;
};

type CreateDocumentPayload = DocumentRequestPayload & {
    documentDate: string;
    documentComment: string;
    deliveryDate?: string;
    referencedDocumentId?: string;
    formats: string[];
};

type UploadDocumentPayload = DocumentRequestPayload & {
    orderVersionId: string;
    format: string;
    mediaId: string | null;
};

type PreviewDocumentPayload = DocumentRequestPayload & {
    documentDate: string;
    documentComment: string;
    deliveryDate?: string;
    referencedDocumentId?: string;
    format: string;
};

type DocumentErrorResponse = {
    errors?: DocumentError[];
};

type DocumentFileResponse = {
    file: Blob;
    fileName: string | null;
};

/**
 * @sw-package after-sales
 * @private
 * @class
 * @extends ApiService
 */
export default class DocumentV2ApiService extends ApiService {
    private listener: DocumentListener = () => {};

    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'document-v2') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'documentV2ApiService';
    }

    public getAvailableTypes(): Promise<AvailableDocumentTypesResponse> {
        return this.httpClient
            .get<AvailableDocumentTypesResponse>('/_action/order/document-v2/available-types', {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse<AvailableDocumentTypesResponse>(response));
    }

    public createDocument(
        orderId: string,
        documentTypeName: string,
        formats: string[],
        documentNumber: string,
        documentDate: string,
        documentComment: string = '',
        deliveryDate: string | null = null,
        referencedDocumentId: string | null = null,
        additionalHeaders: Record<string, string> = {},
    ): Promise<DocumentCreateResponse | void> {
        const headers = this.getBasicHeaders(additionalHeaders);
        const payload: CreateDocumentPayload = {
            orderId,
            documentType: documentTypeName,
            formats,
            documentNumber,
            documentDate,
            documentComment,
        };

        if (documentTypeName === DOCUMENT_TYPES.DELIVERY_NOTE && deliveryDate) {
            payload.deliveryDate = deliveryDate;
        }

        if (referencedDocumentId) {
            payload.referencedDocumentId = referencedDocumentId;
        }

        return this.httpClient
            .post<DocumentCreateResponse>('/_action/order/document-v2/create', payload, { headers })
            .then((response) => ApiService.handleResponse<DocumentCreateResponse>(response))
            .catch((error: AxiosError<DocumentErrorResponse>) => {
                this.emitDocumentFailed(error.response?.data.errors?.pop());
            });
    }

    public uploadDocument(
        orderId: string,
        orderVersionId: string,
        documentTypeName: string,
        format: string,
        documentNumber: string,
        mediaId: string | null = null,
        file: File | null = null,
        additionalHeaders: Record<string, string> = {},
    ): Promise<DocumentCreateResponse | void> {
        const headers = this.getBasicHeaders(additionalHeaders);
        const payload: UploadDocumentPayload = {
            orderId,
            orderVersionId,
            documentType: documentTypeName,
            format,
            documentNumber,
            mediaId,
        };

        let request: Promise<AxiosResponse<DocumentCreateResponse>>;

        if (typeof File !== 'undefined' && file instanceof File) {
            headers['Content-Type'] = file.type;

            const extensionSeparatorIndex = file.name.lastIndexOf('.');

            request = this.httpClient.post<DocumentCreateResponse>('/_action/order/document-v2/upload', file, {
                params: {
                    ...payload,
                    extension: extensionSeparatorIndex === -1 ? '' : file.name.slice(extensionSeparatorIndex + 1),
                    fileName: extensionSeparatorIndex === -1 ? file.name : file.name.slice(0, extensionSeparatorIndex),
                },
                headers,
            });
        } else {
            request = this.httpClient.post<DocumentCreateResponse>('/_action/order/document-v2/upload', payload, {
                headers,
            });
        }

        return request
            .then((response) => ApiService.handleResponse<DocumentCreateResponse>(response))
            .catch((error: AxiosError<DocumentErrorResponse>) => {
                this.emitDocumentFailed(error.response?.data.errors?.pop());
            });
    }

    public previewDocument(
        orderId: string,
        documentTypeName: string,
        format: string,
        documentNumber: string,
        documentDate: string,
        documentComment = '',
        additionalHeaders: Record<string, string> = {},
    ): Promise<DocumentFileResponse | void> {
        const headers = this.getBasicHeaders(additionalHeaders);
        const payload: PreviewDocumentPayload = {
            orderId,
            documentType: documentTypeName,
            format,
            documentNumber,
            documentDate,
            documentComment,
        };

        return this.httpClient
            .post<Blob>('/_action/order/document-v2/preview', payload, {
                responseType: 'blob',
                headers,
            })
            .then((response) => {
                return {
                    file: ApiService.handleResponse<Blob>(response),
                    fileName: fileReaderUtils.getFilenameFromResponse(response as { headers?: { [key: string]: string } }),
                };
            })
            .catch(async (error: AxiosError<Blob>) => {
                if (!error.response) {
                    return;
                }

                const errorObject = (JSON.parse(await error.response.data.text()) as DocumentErrorResponse).errors?.pop();
                this.emitDocumentFailed(errorObject);
            });
    }

    public getDocument(documentId: string, format = 'pdf'): Promise<DocumentFileResponse> {
        return this.httpClient
            .get<Blob>(`/_action/order/document-v2/${documentId}/download/${format}`, {
                responseType: 'blob',
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return {
                    file: ApiService.handleResponse<Blob>(response),
                    fileName: fileReaderUtils.getFilenameFromResponse(response as { headers?: { [key: string]: string } }),
                };
            });
    }

    public getDocumentArchive(documentId: string): Promise<DocumentFileResponse> {
        return this.httpClient
            .get<Blob>(`/_action/order/document-v2/${documentId}/download-archive`, {
                responseType: 'blob',
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return {
                    file: ApiService.handleResponse<Blob>(response),
                    fileName: fileReaderUtils.getFilenameFromResponse(response as { headers?: { [key: string]: string } }),
                };
            });
    }

    public setListener(callback: DocumentListener): void {
        this.listener = callback;
    }

    private createDocumentEvent(action: string, payload?: DocumentError): DocumentEvent {
        return { action, payload };
    }

    private emitDocumentFailed(payload?: DocumentError): void {
        if (!payload) {
            return;
        }

        this.listener(this.createDocumentEvent(DocumentEvents.DOCUMENT_FAILED, payload));
    }
}

/**
 * @private
 */
export type {
    AvailableDocumentTypesResponse,
    DocumentCreateResponse,
    DocumentError,
    DocumentEvent,
    DocumentListener,
    DocumentTypeFormats,
};
