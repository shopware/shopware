/**
 * @sw-package framework
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * @private
 */
export type ContentLayoutDraft = {
    versionId: string;
    createdAt: string;
    updatedAt: string | null;
};

/**
 * Gateway for the draft version lifecycle of a content layout.
 */
class ContentSystemLayoutDraftApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'content-system') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'contentSystemLayoutDraftService';
    }

    getDrafts(layoutId: string): Promise<ContentLayoutDraft[]> {
        return this.httpClient
            .get<{ drafts: ContentLayoutDraft[] }>(`${this.layoutUrl(layoutId)}/drafts`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse<{ drafts: ContentLayoutDraft[] }>(response).drafts ?? []);
    }

    createDraft(layoutId: string): Promise<string> {
        return this.httpClient
            .post<{ versionId: string }>(
                this.draftUrl(layoutId),
                {},
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then((response) => ApiService.handleResponse<{ versionId: string }>(response).versionId);
    }

    publish(layoutId: string, versionId: string): Promise<void> {
        return this.httpClient
            .post(
                `${this.draftUrl(layoutId)}/${versionId}/publish`,
                {},
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then(() => undefined);
    }

    discard(layoutId: string, versionId: string): Promise<void> {
        return this.httpClient
            .delete(`${this.draftUrl(layoutId)}/${versionId}`, {
                headers: this.getBasicHeaders(),
            })
            .then(() => undefined);
    }

    private layoutUrl(layoutId: string): string {
        return `/_action/content-system/layout/${layoutId}`;
    }

    private draftUrl(layoutId: string): string {
        return `${this.layoutUrl(layoutId)}/draft`;
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ContentSystemLayoutDraftApiService;
