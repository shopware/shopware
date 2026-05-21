import ApiService from '../api.service';

/**
 * Admin API gateway for the Universal Commerce Protocol (UCP) endpoints.
 *
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @class
 * @extends ApiService
 * @sw-package fundamentals@framework
 */
class UcpAdminApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, '_admin/ucp');
        this.name = 'ucpAdminService';
    }

    listSalesChannels() {
        return this.httpClient
            .get('/_admin/ucp/sales-channels', { headers: this.getBasicHeaders() })
            .then((r) => ApiService.handleResponse(r));
    }

    getConfig(salesChannelId) {
        return this.httpClient
            .get(`/_admin/ucp/sales-channels/${salesChannelId}/config`, { headers: this.getBasicHeaders() })
            .then((r) => ApiService.handleResponse(r));
    }

    writeConfig(salesChannelId, payload) {
        return this.httpClient
            .put(`/_admin/ucp/sales-channels/${salesChannelId}/config`, payload, { headers: this.getBasicHeaders() })
            .then((r) => ApiService.handleResponse(r));
    }

    listKeys(salesChannelId) {
        return this.httpClient
            .get(`/_admin/ucp/sales-channels/${salesChannelId}/keys`, { headers: this.getBasicHeaders() })
            .then((r) => ApiService.handleResponse(r));
    }

    createKey(salesChannelId, { algorithm = 'ES256', rotate = true } = {}) {
        return this.httpClient
            .post(
                `/_admin/ucp/sales-channels/${salesChannelId}/keys`,
                { algorithm, rotate },
                { headers: this.getBasicHeaders() }
            )
            .then((r) => ApiService.handleResponse(r));
    }

    retireKey(salesChannelId, kid) {
        return this.httpClient
            .post(
                `/_admin/ucp/sales-channels/${salesChannelId}/keys/${kid}/retire`,
                {},
                { headers: this.getBasicHeaders() }
            )
            .then((r) => ApiService.handleResponse(r));
    }

    deleteKey(salesChannelId, kid) {
        return this.httpClient
            .delete(`/_admin/ucp/sales-channels/${salesChannelId}/keys/${kid}`, { headers: this.getBasicHeaders() })
            .then((r) => ApiService.handleResponse(r));
    }

    previewProfile(salesChannelId) {
        return this.httpClient
            .get(`/_admin/ucp/sales-channels/${salesChannelId}/profile-preview`, { headers: this.getBasicHeaders() })
            .then((r) => ApiService.handleResponse(r));
    }

    listPlatformProfiles() {
        return this.httpClient
            .get('/_admin/ucp/platform-profiles', { headers: this.getBasicHeaders() })
            .then((r) => ApiService.handleResponse(r));
    }

    deletePlatformProfile(id) {
        return this.httpClient
            .delete(`/_admin/ucp/platform-profiles/${id}`, { headers: this.getBasicHeaders() })
            .then((r) => ApiService.handleResponse(r));
    }
}

export default UcpAdminApiService;
