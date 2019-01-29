import ApiService from '../api.service';

/**
 * Gateway for the API end point "system_config"
 * @class
 * @extends ApiService
 */
class ConfigFormRendererApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'system-config') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'configFormRendererService';
    }

    getConfig(additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient
            .get('_action/core/system-config', {
                params: additionalParams,
                headers: this.getBasicHeaders(additionalHeaders)
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default ConfigFormRendererApiService;
