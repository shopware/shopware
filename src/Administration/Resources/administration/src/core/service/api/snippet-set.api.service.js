import ApiService from '../api.service';

/**
 * Gateway for the API end point "snippet-set"
 * @class
 * @extends ApiService
 */
class SnippetSetApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'snippet-set') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'snippetSetService';
    }

    /**
     * @returns {Promise<T>}
     */
    getCustomList(
        page = 1,
        limit = 25,
        filters = {}
    ) {
        const headers = this.getBasicHeaders();
        const defaultFilters = {
            isCustom: false,
            emptySnippets: false,
            term: null,
            namespaces: [],
            authors: [],
            translationKeys: []
        };
        filters = { ...defaultFilters, ...filters };

        return this.httpClient
            .post(
                `/_action/${this.getApiBasePath()}`,
                { page, limit, filters },
                { headers }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
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
