/**
 * @sw-package framework
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * @private
 */
export type ContentLayoutBranch = {
    id: string;
    name: string;
    base: string;
    head: string;
    createdAt: string;
    updatedAt: string;
};

/**
 * @private
 */
export type ContentLayoutRevisionSummary = {
    id: string;
    parent: string | null;
    createdAt: string;
    createdBy: string | null;
    branches: string[];
    published: boolean;
};

/**
 * @private
 */
export type ContentLayoutBranchWithLayout = {
    branch: ContentLayoutBranch;
    layout: ContentElementNode[];
};

/**
 * @private
 */
export type ContentLayoutSavedRevision = ContentLayoutBranchWithLayout & {
    revision: ContentLayoutRevisionSummary;
};

/**
 * @private
 */
export type ContentLayoutRevisionHistory = {
    published: string;
    revisions: ContentLayoutRevisionSummary[];
};

/**
 * @private
 */
export type ContentLayoutCreateBranchPayload = {
    name?: string;
    fromRevisionId?: string;
};

/**
 * @private
 */
export type ContentLayoutSaveRevisionPayload = {
    layout: ContentElementNode[];
    expectedHead: string;
    name?: string;
};

/**
 * @private
 */
export type ContentLayoutPublishPayload = {
    revisionId: string;
    deleteBranchId?: string;
};

/**
 * Gateway for the revision graph of a content layout: branches, revisions and publishing.
 */
class ContentSystemLayoutRevisionApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'content-system') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'contentSystemLayoutRevisionService';
    }

    getBranches(layoutId: string): Promise<ContentLayoutBranch[]> {
        return this.httpClient
            .get<{ branches: ContentLayoutBranch[] }>(this.branchesUrl(layoutId), {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse<{ branches: ContentLayoutBranch[] }>(response).branches ?? []);
    }

    createBranch(layoutId: string, payload: ContentLayoutCreateBranchPayload = {}): Promise<ContentLayoutBranch> {
        return this.httpClient
            .post<{ branch: ContentLayoutBranch }>(this.branchesUrl(layoutId), payload, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse<{ branch: ContentLayoutBranch }>(response).branch);
    }

    getBranch(layoutId: string, branchId: string): Promise<ContentLayoutBranchWithLayout> {
        return this.httpClient
            .get<ContentLayoutBranchWithLayout>(this.branchUrl(layoutId, branchId), {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse<ContentLayoutBranchWithLayout>(response));
    }

    deleteBranch(layoutId: string, branchId: string): Promise<void> {
        return this.httpClient
            .delete(this.branchUrl(layoutId, branchId), {
                headers: this.getBasicHeaders(),
            })
            .then(() => undefined);
    }

    saveRevision(
        layoutId: string,
        branchId: string,
        payload: ContentLayoutSaveRevisionPayload,
    ): Promise<ContentLayoutSavedRevision> {
        return this.httpClient
            .post<ContentLayoutSavedRevision>(`${this.branchUrl(layoutId, branchId)}/revisions`, payload, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse<ContentLayoutSavedRevision>(response));
    }

    getRevisions(layoutId: string): Promise<ContentLayoutRevisionHistory> {
        return this.httpClient
            .get<ContentLayoutRevisionHistory>(`${this.layoutUrl(layoutId)}/revisions`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse<ContentLayoutRevisionHistory>(response));
    }

    publish(layoutId: string, payload: ContentLayoutPublishPayload): Promise<void> {
        return this.httpClient
            .post(`${this.layoutUrl(layoutId)}/publish`, payload, {
                headers: this.getBasicHeaders(),
            })
            .then(() => undefined);
    }

    private layoutUrl(layoutId: string): string {
        return `/_action/content-system/layout/${layoutId}`;
    }

    private branchesUrl(layoutId: string): string {
        return `${this.layoutUrl(layoutId)}/branches`;
    }

    private branchUrl(layoutId: string, branchId: string): string {
        return `${this.branchesUrl(layoutId)}/${branchId}`;
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ContentSystemLayoutRevisionApiService;
