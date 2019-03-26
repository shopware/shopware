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

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/login`, { shopwareId, password }, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    checkLogin() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/checklogin`, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getLicenseList() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/licenses`, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getUpdateList() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/updates`, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    downloadPlugin(pluginName) {
        const headers = this.getBasicHeaders();
        const params = {
            pluginName: pluginName
        };

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/download`, { params, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default StoreApiService;
