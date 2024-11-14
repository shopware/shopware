import type { AxiosInstance } from 'axios';
import ApiService from '../api.service';
import type { LoginService } from '../login.service';

/**
 * Gateway for the API end point "order"
 * Uses the _proxy endpoint of the admin api to connect to the store-api endpoint cart
 * @class
 * @extends ApiService
 */
class CheckoutStoreService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'checkout') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'checkoutStoreService';
    }

    checkout(salesChannelId: string, contextToken: string, additionalParams = {}, additionalHeaders = {}) {
        const route = `_proxy-order/${salesChannelId}`;
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken,
        };
        return this.httpClient.post(route, {}, { ...additionalParams, headers });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default CheckoutStoreService;
