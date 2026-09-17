/**
 * @sw-package framework
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * One entry of the curated catalogue of entity data an author may bind a mappable property to.
 *
 * `label` and `description` are snippet keys, not display strings.
 *
 * @private
 */
export interface ContentSystemMappingCandidate {
    path: string;
    label: string;
    description: string;
    group: string;
    valueType: string;
    contextType: 'single' | 'collection';
    projection: string | null;
}

/**
 * @private
 */
export interface ContentSystemMappingCandidateResponse {
    mappingCandidates: Record<string, ContentSystemMappingCandidate[]>;
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
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ContentSystemMappingCandidateApiService;
