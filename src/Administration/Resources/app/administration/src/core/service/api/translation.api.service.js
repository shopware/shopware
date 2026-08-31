import ApiService from '../api.service';

/**
 * Gateway for the API end point "translation".
 * Mirrors the PHP controller Shopware\Core\System\Snippet\Api\TranslationController:
 * every method maps 1:1 to one of its routes (list, install, update, delete).
 * @class
 * @extends ApiService
 * @sw-package discovery
 * @private
 */
class TranslationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'translation') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'translationService';
    }

    /**
     * Get all configured translations together with their local install metadata
     * (last update and translation progress).
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    getList(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get(`/_action/${this.apiEndpoint}/list`, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Get the translation meta information (built-in locales, documentation and community
     * translation URLs, completeness threshold) that is independent of the configured locales.
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    getMeta(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get(`/_action/${this.apiEndpoint}/meta`, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Install the translations for the given locales, or for all configured locales.
     *
     * @param {Object} [options = {}]
     * @param {string[]} [options.locales = []]
     * @param {boolean} [options.all = false]
     * @param {boolean} [options.activate = true]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    install({ locales = [], all = false, activate = true } = {}, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(`/_action/${this.apiEndpoint}/install`, { locales, all, activate }, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Update all installed translations to their latest available version.
     *
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    update(additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(`/_action/${this.apiEndpoint}/update`, {}, { headers }).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    /**
     * Remove the installed translation for a single locale.
     *
     * @param {string} locale
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    deleteTranslation(locale, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .delete(`/_action/${this.apiEndpoint}/${locale}`, {
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default TranslationApiService;
