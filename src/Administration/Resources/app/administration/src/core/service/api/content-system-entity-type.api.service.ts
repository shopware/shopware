/**
 * @sw-package framework
 */

import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

type ContentSystemEntityTypeResponse = {
    entityTypes?: unknown;
};

/**
 * Gateway for content system entity types endpoint.
 */
class ContentSystemEntityTypeApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'content-system-entity-types') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'contentSystemEntityTypeService';
    }

    getEntityTypes(): Promise<string[]> {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get<ContentSystemEntityTypeResponse>('/_info/content-system-entity-types.json', { headers })
            .then((response) => {
                const payload = ApiService.handleResponse<ContentSystemEntityTypeResponse>(response);

                return Array.isArray(payload.entityTypes)
                    ? payload.entityTypes.filter((entityType): entityType is string => typeof entityType === 'string')
                    : [];
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ContentSystemEntityTypeApiService;
