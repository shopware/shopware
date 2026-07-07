/**
 * @sw-package framework
 */

import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import type { ContentSystemElementTypePropertyAdminUi } from './content-system-element-type.api.service';
import ApiService from '../api.service';

type ContentSystemStyleOptionPrimitive = string | number | boolean | null;

/**
 * @private
 */
export interface ContentSystemStyleOptionSpecification {
    type: string;
    enum: Array<string | number | boolean> | null;
    range: {
        min?: number;
        max?: number;
    } | null;
    maxLength: number | null;
    default: ContentSystemStyleOptionPrimitive;
    breakpointAware: boolean;
    adminUI: ContentSystemElementTypePropertyAdminUi | null;
}

/**
 * @private
 */
export interface ContentSystemStyleOptionResponse {
    styleOptions: Record<string, ContentSystemStyleOptionSpecification>;
}

/**
 * Gateway for content system style options endpoint.
 */
class ContentSystemStyleOptionApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'content-system-style-options') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'contentSystemStyleOptionService';
    }

    getStyleOptions(): Promise<Record<string, ContentSystemStyleOptionSpecification>> {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get<ContentSystemStyleOptionResponse>('/_info/content-system-style-options.json', {
                headers,
            })
            .then((response) => {
                const payload = ApiService.handleResponse<ContentSystemStyleOptionResponse>(response);
                const styleOptions = payload.styleOptions;

                if (typeof styleOptions !== 'object' || styleOptions === null || Array.isArray(styleOptions)) {
                    return {};
                }

                return styleOptions;
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ContentSystemStyleOptionApiService;
