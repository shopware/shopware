import ApiService from './api.service';

/**
 * Gateway for the API end point "configuration-group"
 * @class
 * @extends ApiService
 */
class ConfigurationGroupApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'configuration-group') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default ConfigurationGroupApiService;
