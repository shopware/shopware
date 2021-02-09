import ApiService from 'src/core/service/api.service';
/**
 * Gateway for the API end point "plugin"
 * @class
 * @extends ApiService
 */
export default class ExtensionApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'extension') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'extensionService';
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

    install(extensionName) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/install`, {}, { params: { extensionName }, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    uninstall(extensionName, { keepUserData = true }) {
        const headers = this.getBasicHeaders();

        const requestParams = { extensionName, keepUserData: keepUserData ? 1 : 0 };
        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/uninstall`, {}, { params: requestParams, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    activate(extensionName) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/activate`, {}, { params: { extensionName }, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    deactivate(extensionName) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/deactivate`, {}, { params: { extensionName }, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    update(extensionName) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/update`, {}, { params: { extensionName }, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    delete(extensionName) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/delete`, {}, { params: { extensionName }, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    refresh() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/refresh`, {}, { params: { }, headers })
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
