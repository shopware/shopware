const ApiService = Shopware.Classes.ApiService;

/**
 * Gateway for the API end point "language-plugins"
 * @class
 * @extends ApiService
 */
class LanguagePluginApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'language-plugins') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'languagePluginService';
    }

    /**
     * Get language-plugins
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    getPlugins(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get(`/_action/store/${this.apiEndpoint}`, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default LanguagePluginApiService;
