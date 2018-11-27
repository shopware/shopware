import ApiService from './api.service';

/**
 * Gateway for the API end point "snippet-set"
 * @class
 * @extends ApiService
 */
class SnippetSetApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'snippet-set') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default SnippetSetApiService;
