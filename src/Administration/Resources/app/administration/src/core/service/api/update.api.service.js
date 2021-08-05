const ApiService = Shopware.Classes.ApiService;

/**
 * Gateway for the API end point "update"
 * @class
 * @extends ApiService
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

    pluginCompatibility() {
        const headers = this.getBasicHeaders();
        const params = this.getBasicParams();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/plugin-compatibility`, { params, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    downloadUpdate(offset) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/download-latest-update?offset=${offset}`, { headers })
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

    unpackUpdate(offset) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `/_action/${this.getApiBasePath()}/unpack?offset=${offset}`,
                { headers },
            ).then((response) => {
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

export default UpdateService;
