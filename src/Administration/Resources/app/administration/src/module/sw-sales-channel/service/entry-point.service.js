/**
 * @package buyers-experience
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class EntryPointService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.name = 'entrypointService';
    }

    list(salesChannelId) {
        const headers = this.getAuthHeaders();

        return this.httpClient
            .get(`/_action/sales-channel/${salesChannelId}/entrypoint`, { headers })
            .then(response => response.data);
    }

    getAuthHeaders() {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }
}
