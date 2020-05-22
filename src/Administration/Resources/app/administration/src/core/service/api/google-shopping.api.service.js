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

    getAccountProfile(salesChannelId, additionalParams = {}, additionalHeaders = {}) {
        const route = `/_action/sales-channel/${salesChannelId}/google-shopping/account/profile`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.get(route, { additionalParams, headers });
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

    getMerchantStatus(salesChannelId, additionalParams = {}, additionalHeaders = {}) {
        const route = `/_action/sales-channel/${salesChannelId}/google-shopping/merchant/status`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.get(route, { additionalParams, headers });
    }

    verifyStore(salesChannelId, additionalParams = {}, additionalHeaders = {}) {
        const route = `/_action/sales-channel/${salesChannelId}/google-shopping/eligibility-requirements`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.get(route, { additionalParams, headers });
    }

    saveTermsOfService(salesChannelId, acceptance, additionalParams = {}, additionalHeaders = {}) {
        const route = `/_action/sales-channel/${salesChannelId}/google-shopping/account/accept-term-of-service`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, { acceptance }, { additionalParams, headers });
    }

    setupShipping(salesChannelId, rate, additionalParams = {}, additionalHeaders = {}) {
        const route = `/_action/sales-channel/${salesChannelId}/google-shopping/merchant/setup-shipping`;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(route, { flatRate: rate }, { additionalParams, headers });
    }
}

export default GoogleShoppingService;
