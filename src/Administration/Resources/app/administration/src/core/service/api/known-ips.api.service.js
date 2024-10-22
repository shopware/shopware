/**
 * @package admin
 */

import ApiService from '../api.service';

/**
 * @class
 * @extends ApiService
 */
class KnownIpsApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'known-ips') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'knownIpsService';
    }

    /**
     * Get snippets
     *
     * @returns {Promise<Array<{name: String, value: String}>>}
     */
    getKnownIps() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get('/_admin/known-ips', {
                headers,
            })
            .then((response) => {
                return response.data.ips;
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default KnownIpsApiService;
