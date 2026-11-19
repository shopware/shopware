/**
 * @sw-package inventory
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * @private
 */
export type DocumentChanges = { title?: string | null; mediaId?: EntityKey<'media'> | null };

/**
 * @private
 */
export type DocumentVariantOperation = { mediaId: string; title: string | null; changes?: DocumentChanges };

/**
 * @private
 */
export default class ProductDocumentVariantApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService) {
        super(httpClient, loginService, '_action/product', 'application/json');
        this.name = 'productDocumentVariantService';
    }

    public async sync(
        productId: string,
        operations: DocumentVariantOperation[],
        dryRun = false,
        languageId = Shopware.Context.api.languageId,
    ): Promise<string[]> {
        if (!operations.length) {
            return [];
        }

        const { data } = await this.httpClient.post<{ variantIds: string[] }>(
            `${this.getApiBasePath(productId)}/document-variants`,
            { operations, dryRun },
            { headers: this.getBasicHeaders(languageId ? { 'sw-language-id': languageId } : {}) },
        );

        return data.variantIds;
    }
}
