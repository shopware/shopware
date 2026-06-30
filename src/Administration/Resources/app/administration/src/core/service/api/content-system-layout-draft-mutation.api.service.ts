/**
 * @sw-package framework
 */

import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * @private
 */
export type ContentLayoutDraftMutationElement = {
    id: string;
    component: string;
    properties?: Record<string, unknown>;
    data_requirements?: unknown;
    provides_context?: unknown;
    accepts_context?: unknown;
    slots?: Record<string, ContentLayoutDraftMutationElement[]>;
};

type ContentLayoutDraftMutationEnvelope = {
    layout: ContentLayoutDraftMutationElement[];
    rootSource: string | null;
};

/**
 * @private
 */
export type ContentLayoutDraftInsertPayload = ContentLayoutDraftMutationEnvelope & {
    type: string;
    parentElementId?: string | null;
    slot?: string | null;
    index?: number | null;
};

/**
 * @private
 */
export type ContentLayoutDraftRemovePayload = ContentLayoutDraftMutationEnvelope & {
    elementId: string;
};

/**
 * @private
 */
export type ContentLayoutDraftDuplicatePayload = ContentLayoutDraftMutationEnvelope & {
    elementId: string;
    index?: number | null;
};

type ContentLayoutDraftMutationDiagnostics = {
    wellFormed: boolean;
    resolvable: boolean;
    violations: unknown[];
};

/**
 * @private
 */
export type ContentLayoutDraftMutationResponse = {
    layout: ContentLayoutDraftMutationElement[];
    resolutions: Record<string, unknown>;
    diagnostics: ContentLayoutDraftMutationDiagnostics;
    affectedElementIds: string[];
    orphaned: ContentLayoutDraftMutationElement[];
    droppedWiring: string[];
    droppedProperties: Record<string, unknown>;
};

/**
 * Gateway for stateless content layout draft mutations.
 */
class ContentSystemLayoutDraftMutationApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'content-system') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'contentSystemLayoutDraftMutationService';
    }

    insertElement(payload: ContentLayoutDraftInsertPayload): Promise<ContentLayoutDraftMutationResponse> {
        return this.mutate('insert-element', payload);
    }

    removeElement(payload: ContentLayoutDraftRemovePayload): Promise<ContentLayoutDraftMutationResponse> {
        return this.mutate('remove-element', payload);
    }

    duplicateElement(payload: ContentLayoutDraftDuplicatePayload): Promise<ContentLayoutDraftMutationResponse> {
        return this.mutate('duplicate-element', payload);
    }

    private mutate(
        operation: string,
        payload: ContentLayoutDraftMutationEnvelope & Record<string, unknown>,
    ): Promise<ContentLayoutDraftMutationResponse> {
        return this.httpClient
            .post<ContentLayoutDraftMutationResponse>(`/_action/content-system/layout/${operation}`, payload, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse<ContentLayoutDraftMutationResponse>(response));
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ContentSystemLayoutDraftMutationApiService;
