import ApiService from '../api.service';

/**
 * Gateway for the API end point "plugin"
 * @class
 * @extends ApiService
 */
class PluginApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'plugin') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'pluginService';
    }

    upload(formData) {
        const additionalHeaders = { 'Content-Type': 'application/zip' };
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(
            `/_action/${this.getApiBasePath()}/upload`,
            formData,
            { headers }
        )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    install(pluginName) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath(pluginName)}/install`, {}, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    uninstall(pluginName) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath(pluginName)}/uninstall`, {}, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    activate(pluginName) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath(pluginName)}/activate`, {}, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    deactivate(pluginName) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath(pluginName)}/deactivate`, {}, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    update(pluginName) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath(pluginName)}/update`, {}, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    delete(pluginName) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath(pluginName)}/delete`, {}, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getLastUpdates() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/lastUpdates`, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default PluginApiService;
