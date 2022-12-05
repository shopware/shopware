const ApiService = Shopware.Classes.ApiService;

/**
 * @package merchant-services
 *
 * Gateway for the API end point "frw"
 * @private
 * @class
 * @extends ApiService
 */
class FirstRunWizardApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'frw') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'firstRunWizardService';
    }

    getBasicHeaders(additionalHeaders = {}) {
        return {
            ...super.getBasicHeaders(additionalHeaders),
            'sw-language-id': Shopware.Context.api.languageId,
        };
    }

    /**
     * Check shopwareId
     *
     * @param {Object} [payload = {}]
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    checkShopwareId(payload = {}, additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(`/_action/store/${this.apiEndpoint}/login`, payload, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Get license domains
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    getLicenseDomains(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get('/_action/store/license-domains', {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Verify license domain
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    verifyLicenseDomain(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post('/_action/store/verify-license-domain', {}, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Set wizard start message
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    setFRWStart(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post('/_action/store/frw/start', {}, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Set wizard finish message
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    setFRWFinish(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post('/_action/store/frw/finish', {}, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

/**
 * @private
 * @package merchant-services
 */
export default FirstRunWizardApiService;
