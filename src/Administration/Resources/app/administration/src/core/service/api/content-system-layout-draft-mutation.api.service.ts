/**
 * @sw-package framework
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import type { ContentElementNode } from '../content-element.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

type ContentLayoutDraftMutationEnvelope = {
    layout: ContentElementNode[];
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

/**
 * @private
 */
export type ContentLayoutDraftMovePayload = ContentLayoutDraftMutationEnvelope & {
    elementId: string;
    newParentId?: string | null;
    newSlot?: string | null;
    index?: number | null;
};

type ContentLayoutDraftMutationDiagnostics = {
    wellFormed: boolean;
    resolvable: boolean;
    violations: unknown[];
};

/**
 * How a reference property is (or could be) filled: `parent` (an ancestor's provider), `root` (the layout's
 * root-ambient context), `loader` (a data loader), or `stored` (the element's own applied wiring).
 *
 * @private
 */
export type ContentSystemResolutionCandidate = {
    origin: 'parent' | 'root' | 'loader' | 'stored';
    contextKey: string | null;
    providerElementId: string | null;
    path: string | null;
    distribution: string | null;
    contextType: 'single' | 'collection' | null;
    loaderSource: string | null;
    configTemplate: Record<string, unknown> | null;
    configComplete: boolean | null;
};

/**
 * A single declared property's resolution, as reported per element by the diagnose/mutation endpoints.
 *
 * @private
 */
export type ContentSystemPropertyResolution = {
    key: string;
    kind: 'primitive' | 'reference';
    required: boolean;
    type: string | null;
    default: unknown;
    fqcn: string | null;
    resolved: ContentSystemResolutionCandidate | null;
    candidates: ContentSystemResolutionCandidate[];
};

/**
 * @private
 */
export type ContentLayoutDraftMutationResponse = {
    layout: ContentElementNode[];
    resolutions: Record<string, ContentSystemPropertyResolution[]>;
    diagnostics: ContentLayoutDraftMutationDiagnostics;
    affectedElementIds: string[];
    orphaned: ContentElementNode[];
    droppedWiring: string[];
    droppedProperties: Record<string, unknown>;
};

/**
 * Gateway for stateless content layout draft mutations.
 */
class ContentSystemLayoutDraftMutationApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'content-system') {
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

    moveElement(payload: ContentLayoutDraftMovePayload): Promise<ContentLayoutDraftMutationResponse> {
        return this.mutate('move-element', payload);
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
