import ApiService from '../api.service';

/**
 * Gateway for the API end point "snippet"
 * @class
 * @extends ApiService
 */
class SnippetApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'snippet') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'snippetService';
    }

    /**
     * @returns {Promise<T>}
     */
    save(snippet) {
        if (!snippet.hasOwnProperty('id') || snippet.id === null) {
            return this.create(snippet);
        }

        return this.updateById(snippet.id, snippet);
    }

    /**
     * @returns {Promise<T>}
     */
    getByKey(translationKey, page, limit, isCustom = false) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}`, { translationKey, page, limit, isCustom }, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * @returns {Promise<T>}
     */
    getFilter() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/filter`, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default SnippetApiService;
