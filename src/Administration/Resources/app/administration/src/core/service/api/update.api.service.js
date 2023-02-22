/**
 * @deprecated tag:v6.6.0 - Will be private
 * @package admin
 */

const ApiService = Shopware.Classes.ApiService;

/**
 * Gateway for the API end point "update"
 * @class
 * @extends ApiService
 * @package system-settings
 */
class UpdateService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'update') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'updateService';
    }

    checkForUpdates() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/check`, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    checkRequirements() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/check-requirements`, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * @deprecated tag:v6.6.0 - Will be removed
     */
    pluginCompatibility() {
        return {};
    }

    extensionCompatibility() {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/extension-compatibility`, { params, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    downloadRecovery() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/download-recovery`, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    deactivatePlugins(offset, pluginDeactivationStrategy = '') {
        const headers = this.getBasicHeaders();
        const actionUrlPart = `/_action/${this.getApiBasePath()}`;
        const offsetParam = `offset=${offset}&deactivationFilter=${pluginDeactivationStrategy}`;

        return this.httpClient
            .get(`${actionUrlPart}/deactivate-plugins?${offsetParam}`, { headers })
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

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default UpdateService;
