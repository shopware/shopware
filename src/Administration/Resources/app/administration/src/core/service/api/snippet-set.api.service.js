const ApiService = Shopware.Classes.ApiService;

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
    getCustomList(page = 1, limit = 25, filters = {}, sort = {}) {
        const headers = this.getBasicHeaders();

        const defaultSort = {
            sortBy: 'id',
            sortDirection: 'ASC',
        };

        sort = { ...defaultSort, ...sort };

        return this.httpClient
            .post(
                `/_action/${this.getApiBasePath()}`,
                { page, limit, filters, sort },
                { headers },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Call the API to get all available BaseFiles
     *
     * @returns {Promise<T>}
     */
    getBaseFiles() {
        const params = {};
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/baseFile`, { params, headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getAuthors() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/author`, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default SnippetSetApiService;
