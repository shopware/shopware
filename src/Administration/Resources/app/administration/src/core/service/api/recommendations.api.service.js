const ApiService = Shopware.Classes.ApiService;

/**
 * Gateway for the API end point "recommenations"
 * @class
 * @extends ApiService
 */
class RecommendationsApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'recommendations') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'recommendationsService';
    }

    /**
     * Get recommendations
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    getRecommendations(additionalParams = {}, additionalHeaders = {}) {
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

    /**
     * Get recommendations-regions
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    getRecommendationRegions(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get('/_action/store/recommendation-regions', {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default RecommendationsApiService;
