import ApiService from './api.service';

/**
 * Gateway for the API end point "configuration-group-option"
 * @class
 * @extends ApiService
 */
class ConfigurationGroupOptionApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'configuration-group-option') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default ConfigurationGroupOptionApiService;
