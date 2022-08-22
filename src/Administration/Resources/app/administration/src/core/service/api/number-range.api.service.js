const ApiService = Shopware.Classes.ApiService;

/**
 * Gateway for the API end point "number-range"
 * @class
 * @extends ApiService
 */
class NumberRangeApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'number-range') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'numberRangeService';
    }

    /**
     * reserve a number range value
     *
     * @param {string} typeName
     * @param {string} [salesChannelId]
     * @param {boolean} preview [preview=false]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    reserve(typeName, salesChannelId = '', preview = false, additionalHeaders = {}) {
        const urlSuffix = salesChannelId ? `/${salesChannelId}` : '';
        const url = `_action/number-range/reserve/${typeName}${urlSuffix}`;

        const headers = this.getBasicHeaders(additionalHeaders);
        const params = {
            preview: preview,
        };

        return this.httpClient
            .get(url, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * get preview of next number range value
     *
     * @param {string} typeName
     * @param {string} pattern
     * @param {int} start
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    previewPattern(typeName, pattern, start, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);
        const params = {
            pattern: pattern,
            start: start,
        };

        return this.httpClient
            .get(`_action/number-range/preview-pattern/${typeName}`, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default NumberRangeApiService;
