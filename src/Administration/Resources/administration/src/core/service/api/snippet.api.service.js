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

    /**
     * @returns {Promise<T>}
     */
    save(snippet) {
        if (snippet.id === null) {
            return this.create(snippet);
        }
        return this.updateById(snippet.id, snippet);
    }
}

export default SnippetApiService;
