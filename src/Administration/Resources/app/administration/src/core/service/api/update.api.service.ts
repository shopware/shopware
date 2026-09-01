/**
 * @sw-package framework
 * @private
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from 'src/core/service/login.service';
import ApiService from 'src/core/service/api.service';

/**
 * Gateway for the API end point "update"
 * @class
 * @extends ApiService
 * @sw-package framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class UpdateService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'update') {
        super(httpClient, loginService, apiEndpoint);

        this.name = 'updateService';
    }

    checkForUpdates() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get<{
                version: unknown;
                changelog: unknown;
                autoUpdateEnabled?: boolean;
                clusterSetup?: boolean;
            }>(`/_action/${this.getApiBasePath()}/check`, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    checkLicense() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get<{ isValid: boolean }>(`/_action/${this.getApiBasePath()}/check-license`, {
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    extensionCompatibility() {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        return this.httpClient
            .get<Array<{ statusName: string }>>(`/_action/${this.getApiBasePath()}/extension-compatibility`, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    downloadRecovery() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get<unknown>(`/_action/${this.getApiBasePath()}/download-recovery`, {
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    deactivateExtensions(offset: number, extensionDeactivationStrategy = '') {
        const headers = this.getBasicHeaders();
        const actionUrlPart = `/_action/${this.getApiBasePath()}`;
        const offsetParam = `offset=${offset}&deactivationFilter=${extensionDeactivationStrategy}`;

        return this.httpClient
            .get<{ offset: number; total: number }>(`${actionUrlPart}/deactivate-extensions?${offsetParam}`, {
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getBasicParams(additionalParams = {}) {
        const basicParams = {
            language: localStorage.getItem('sw-admin-locale'),
        };

        return { ...basicParams, ...additionalParams };
    }
}
