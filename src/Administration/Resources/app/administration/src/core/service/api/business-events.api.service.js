import ApiService from '../api.service';

/**
 * @package services-settings
 *
 * @class
 * @extends ApiService
 */
class BusinessEventsApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'business-events') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'businessEventService';
    }

    /**
     * Get all business events
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    getBusinessEvents(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get('/_info/events.json', {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default BusinessEventsApiService;
