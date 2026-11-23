/**
 * @private
 * @sw-package inventory
 */
import type { HttpClient } from 'src/core/factory/http-client.types';
import ApiService from 'src/core/service/api.service';
import type { LoginService } from 'src/core/service/login.service';

/**
 * @private
 */
export type ProductDocumentUploadSettings = {
    allowedExtensions: string[];
    allowedMimeTypesByExtension: Record<string, string[]>;
    maxFileSize: number;
};

/**
 * @private
 */
export default class ProductDocumentApiService extends ApiService {
    constructor(
        httpClient: HttpClient,
        loginService: LoginService,
        apiEndpoint = '_action/product/document-upload-settings',
    ) {
        super(httpClient, loginService, apiEndpoint);

        this.name = 'productDocumentApiService';
    }

    async getUploadSettings(): Promise<ProductDocumentUploadSettings> {
        const response = await this.httpClient.get<ProductDocumentUploadSettings>(`/${this.apiEndpoint}`, {
            headers: this.getBasicHeaders(),
        });

        return ApiService.handleResponse(response);
    }
}
