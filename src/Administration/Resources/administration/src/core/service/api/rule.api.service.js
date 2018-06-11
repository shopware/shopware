import ApiService from './api.service';

/**
 * Gateway for the API end point "rule"
 * @class
 * @extends ApiService
 */
class RuleApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'rule') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default RuleApiService;
