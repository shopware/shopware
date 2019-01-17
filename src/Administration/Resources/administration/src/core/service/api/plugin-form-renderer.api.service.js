import ApiService from '../api.service';

/**
 * Gateway for the API end point "get-plugin-config"
 * @class
 * @extends ApiService
 */
class PluginFormRendererApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'plugin-system') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'pluginFormRendererService';
    }

    getConfig(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);
        return this.httpClient
            .get('_action/core/plugin-config', {
                params: params,
                headers: headers
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default PluginFormRendererApiService;
