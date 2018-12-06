import ApiService from './api.service';

/**
 * Gateway for the API end point "snippet"
 * @class
 * @extends ApiService
 */
class SnippetApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'snippet') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default SnippetApiService;
