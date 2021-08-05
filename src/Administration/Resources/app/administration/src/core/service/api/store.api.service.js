import ApiService from '../api.service';

/**
 * Gateway for the API end point "store"
 * @class
 * @extends ApiService
 */
class StoreApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'store') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'storeService';
    }

    ping() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/ping`, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    login(shopwareId, password) {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/login`, { shopwareId, password }, { params, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    checkLogin() {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/checklogin`, {}, { params, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    logout() {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/logout`, {}, { params, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getLicenseList() {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/licenses`, { params, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getUpdateList() {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/updates`, { params, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * @deprecated tag:v6.5.0 for updating and installing plugins use downloadAndUpdatePlugin
     * TODO: remove param onlyDownload with removing deprecation and remove the source code after
     * the if statement "onlyDownload". It should now only download the plugin and do not trigger a update.
     */
    downloadPlugin(pluginName, unauthenticated = false, onlyDownload = false) {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams({
            pluginName: pluginName,
        });
        if (unauthenticated) {
            params.unauthenticated = true;
        }

        // TODO: remove if condition but keep content of the block
        if (onlyDownload) {
            return this.httpClient
                .get(`/_action/${this.getApiBasePath()}/download`, { params, headers })
                .then((response) => {
                    return ApiService.handleResponse(response);
                });
        }

        // TODO: remove this when removing deprecation
        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/download`, { params, headers })
            .then(() => {
                return this.httpClient
                    .post('/_action/plugin/update', null, { params, headers });
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    downloadAndUpdatePlugin(pluginName, unauthenticated = false) {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams({
            pluginName: pluginName,
        });
        if (unauthenticated) {
            params.unauthenticated = true;
        }

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/download`, { params, headers })
            .then(() => {
                return this.httpClient
                    .post('/_action/plugin/update', null, { params, headers });
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getLicenseViolationList() {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/plugin/search`, null, { params, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getBasicParams(additionalParams = {}) {
        const basicParams = {
            language: localStorage.getItem('sw-admin-locale'),
        };

        return Object.assign({}, basicParams, additionalParams);
    }
}

export default StoreApiService;
