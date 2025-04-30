/**
 * @sw-package framework
 */

import ApiService from '../api.service';

/**
 * Gateway for the API end point "message-stats"
 * @class
 * @extends ApiService
 */
class MessageStatsApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'message-stats') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'messageStatsService';
    }

    /**
     * Get message statistics
     *
     * @returns {Promise<Object>}
     */
    getStats() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get('/_info/message-stats.json', {
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default MessageStatsApiService; 