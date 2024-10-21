import ApiService from '../api.service';

/**
 * Gateway for the API end point "snippet"
 * @class
 * @extends ApiService
 * @package services-settings
 */
class SnippetApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'snippet') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'snippetService';
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

        return this.httpClient.get(`/_action/${this.getApiBasePath()}/filter`, { headers }).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    /**
     * Get snippets
     *
     * @returns {Promise<T>}
     */
    getSnippets(localeFactory, code) {
        const headers = this.getBasicHeaders();
        const locale = code || localeFactory.getLastKnownLocale();

        return this.httpClient
            .get(`/_admin/snippets?locale=${locale}`, {
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            })
            .then((allSnippets) => {
                const registry = localeFactory.getLocaleRegistry();

                Object.entries(allSnippets).forEach(
                    ([
                        localeKey,
                        snippets,
                    ]) => {
                        const fnName = registry.has(localeKey) ? 'extend' : 'register';

                        localeFactory[fnName](localeKey, snippets);
                    },
                );
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default SnippetApiService;
