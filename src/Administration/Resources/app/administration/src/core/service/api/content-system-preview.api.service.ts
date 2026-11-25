/**
 * @sw-package framework
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * @private
 */
type ContentSystemPreviewRequestPayload = {
    layout: unknown[];
    entityType: string;
    entityId: string;
    salesChannelId: string;
    languageId?: string | null;
    currencyId?: string | null;
    domainId?: string | null;
    customerId?: string | null;
    queryParameters?: Record<string, unknown>;
};

/**
 * Gateway for content system preview endpoint.
 */
class ContentSystemPreviewApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'content-system') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'contentSystemPreviewService';
    }

    previewEntityUrl(payload: ContentSystemPreviewRequestPayload): Promise<string> {
        return this.httpClient
            .post<{ url?: string }>('/_action/content-system/preview/entity/url', payload, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                const data = ApiService.handleResponse<{ url?: string }>(response);

                return data.url ?? '';
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ContentSystemPreviewApiService;
