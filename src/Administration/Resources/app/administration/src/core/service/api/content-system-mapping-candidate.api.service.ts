/**
 * @sw-package framework
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * One entry of the curated catalogue of entity data an author may bind a mappable property to.
 *
 * `label` and `description` are snippet keys. Dynamic candidates may additionally carry literal translations
 * sourced from their own configuration.
 *
 * @private
 */
export interface ContentSystemMappingCandidate {
    path: string;
    source: { type: string; id: string; config?: Record<string, unknown>; path?: string };
    label: string;
    description: string;
    group: string;
    valueType: string;
    /** Declared PHP class or interface types that accept this candidate's effective value. */
    compatibleTypes?: string[];
    contextType: 'single' | 'collection';
    labelTranslations?: Record<string, string>;
    descriptionTranslations?: Record<string, string>;
}

/**
 * @private
 */
export interface ContentSystemMappingCandidateResponse {
    mappingCandidates: Record<string, ContentSystemMappingCandidate[]>;
}

/**
 * One token span returned by the PHP inline mapping parser.
 *
 * @private
 */
export interface ContentSystemInlineMappingToken {
    path: string;
    match: string;
    offset: number;
    insideMarkup: boolean;
}

/**
 * @private
 */
export interface ContentSystemInlineMappingParseResponse {
    revision: number;
    tokens: ContentSystemInlineMappingToken[];
}

/**
 * Gateway for the content system mapping candidate catalogue, keyed by root source.
 */
class ContentSystemMappingCandidateApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'content-system-mapping-candidates') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'contentSystemMappingCandidateService';
    }

    getMappingCandidates(): Promise<Record<string, ContentSystemMappingCandidate[]>> {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get<ContentSystemMappingCandidateResponse>('/_info/content-system-mapping-candidates.json', {
                headers,
            })
            .then((response) => {
                const payload = ApiService.handleResponse<ContentSystemMappingCandidateResponse>(response);
                const candidates = payload.mappingCandidates;

                if (typeof candidates !== 'object' || candidates === null || Array.isArray(candidates)) {
                    return {};
                }

                return candidates;
            });
    }

    parseInlineMappingTokens(text: string, revision: number): Promise<ContentSystemInlineMappingParseResponse> {
        return this.httpClient
            .post<ContentSystemInlineMappingParseResponse>(
                '/_action/content-system/inline-mapping/parse',
                { text, revision },
                { headers: this.getBasicHeaders() },
            )
            .then((response) => ApiService.handleResponse<ContentSystemInlineMappingParseResponse>(response));
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ContentSystemMappingCandidateApiService;
