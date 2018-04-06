import ApiService from './api.service';

/**
 * Gateway for the API end point "context-rule"
 * @class
 * @extends ApiService
 */
class ContextRuleApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'context-rule') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default ContextRuleApiService;
