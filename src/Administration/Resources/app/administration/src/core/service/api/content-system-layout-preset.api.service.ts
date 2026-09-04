/**
 * @sw-package framework
 */

import type { AxiosInstance } from 'axios';
import type { ContentElementNode } from '../content-element.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * @private
 */
export interface ContentSystemLayoutPreset {
    id: string;
    name: string;
    description: string | null;
    icon: string | null;
    payload: ContentElementNode[];
}

/**
 * @private
 */
export interface ContentSystemLayoutPresetResponse {
    presets: ContentSystemLayoutPreset[];
}

class ContentSystemLayoutPresetApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'content-system-layout-presets') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'contentSystemLayoutPresetService';
    }

    getPresets(): Promise<ContentSystemLayoutPreset[]> {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get<ContentSystemLayoutPresetResponse>('/_info/content-system-layout-presets.json', {
                headers,
            })
            .then((response) => {
                const payload = ApiService.handleResponse<ContentSystemLayoutPresetResponse>(response);

                return Array.isArray(payload.presets) ? payload.presets : [];
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ContentSystemLayoutPresetApiService;
