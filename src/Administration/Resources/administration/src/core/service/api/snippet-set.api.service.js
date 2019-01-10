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

    /**
     * Call the API to clone the SnippetSet with the given id
     *
     * @param {string} id
     * @returns {Promise<T>}
     */
    cloneSnippetSet(id) {
        return this.clone(id);
    }
}

export default SnippetSetApiService;
