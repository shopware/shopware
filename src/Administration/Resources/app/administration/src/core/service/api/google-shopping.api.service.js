import ApiService from '../api.service';

/**
 * Gateway for the API end point "google-shopping"
 * @class
 * @extends ApiService
 */
class GoogleShoppingService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'google-shopping') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'googleShoppingService';
    }

    connectGoogle(salesChannelId, authCode, additionalParams = {}, additionalHeaders = {}) {
        const route = `/_action/sales-channel/${salesChannelId}/google-shopping/account/connect`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, { code: authCode }, { additionalParams, headers });
    }

    disconnectGoogle(salesChannelId, additionalParams = {}, additionalHeaders = {}) {
        const route = `/_action/sales-channel/${salesChannelId}/google-shopping/account/disconnect`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, {}, { additionalParams, headers });
    }

    getMerchantList(salesChannelId, additionalParams = {}, additionalHeaders = {}) {
        const route = `/_action/sales-channel/${salesChannelId}/google-shopping/merchant/list`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.get(route, { additionalParams, headers });
    }

    assignMerchant(salesChannelId, merchantId, additionalParams = {}, additionalHeaders = {}) {
        const route = `/_action/sales-channel/${salesChannelId}/google-shopping/merchant/assign`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, { merchantId }, { additionalParams, headers });
    }

    unassignMerchant(salesChannelId, additionalParams = {}, additionalHeaders = {}) {
        const route = `/_action/sales-channel/${salesChannelId}/google-shopping/merchant/unassign`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, {}, { additionalParams, headers });
    }

    getMerchantInfo(salesChannelId, additionalParams = {}, additionalHeaders = {}) {
        const route = `/_action/sales-channel/${salesChannelId}/google-shopping/merchant/info`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.get(route, { additionalParams, headers });
    }
}

export default GoogleShoppingService;
