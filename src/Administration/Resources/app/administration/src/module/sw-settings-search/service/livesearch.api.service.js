/**
 * @package system-settings
 */
const ApiService = Shopware.Classes.ApiService;

/**
 * Gateway for the API endpoint "proxy search"
 * @class
 * @extends ApiService
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class LiveSearchService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'search') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'liveSearchService';
    }

    search({ salesChannelId, search }, contextToken, additionalParams = {}, additionalHeaders = {}) {
        const route = `_proxy/store-api/${salesChannelId}/search`;
        const payload = {
            salesChannelId,
            search,
        };
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken,
        };
        return this.httpClient
            .post(route, payload, { additionalParams, headers });
    }
}
