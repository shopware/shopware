import type { AxiosInstance } from 'axios';
import ApiService from '../api.service';
import type { LoginService } from '../login.service';
import type { ContextSwitchParameters } from '../../../module/sw-order/order.types';

/**
 * Gateway for the API end point "sales-channel-context"
 * Uses the _proxy endpoint of the admin api to connect to the store-api endpoint cart
 * @class
 * @extends ApiService
 */
class StoreContextService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'sales-channel-context') {
        super(httpClient, loginService, apiEndpoint, 'application/json');

        this.name = 'contextStoreService';
    }

    updateCustomerContext(
        customerId: string,
        salesChannelId: string,
        contextToken: string,
        additionalParams = {},
        additionalHeaders = {},
        permissions = ['allowProductPriceOverwrites'],
    ) {
        const route = '_proxy/switch-customer';
        const headers = this.getBasicHeaders({
            ...additionalHeaders,
            'sw-context-token': contextToken,
        });

        return this.httpClient.patch(
            route,
            {
                customerId: customerId,
                salesChannelId: salesChannelId,
                permissions: permissions,
            },
            { ...additionalParams, headers },
        );
    }

    updateContext(
        context: ContextSwitchParameters,
        salesChannelId: string,
        contextToken: string | null,
        additionalParams = {},
        additionalHeaders = {},
    ) {
        const route = `_proxy/store-api/${salesChannelId}/context`;
        const headers = this.getBasicHeaders({
            ...additionalHeaders,
            'sw-context-token': contextToken,
        });

        return this.httpClient.patch(route, context, {
            ...additionalParams,
            headers,
        });
    }

    getSalesChannelContext(
        salesChannelId: string,
        contextToken: string | null,
        additionalParams = {},
        additionalHeaders = {},
    ) {
        const route = `_proxy/store-api/${salesChannelId}/context`;
        const headers = this.getBasicHeaders({
            ...additionalHeaders,
            'sw-context-token': contextToken,
        });

        return this.httpClient.get(route, { ...additionalParams, headers });
    }

    generateImitateCustomerToken(customerId: string, salesChannelId: string, additionalParams = {}, additionalHeaders = {}) {
        const route = '_proxy/generate-imitate-customer-token';
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(
            route,
            {
                customerId,
                salesChannelId,
            },
            { ...additionalParams, headers },
        );
    }

    redirectToSalesChannelUrl(salesChannelDomainUrl: string, token: string, customerId: string, userId: string) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${salesChannelDomainUrl}/account/login/imitate-customer`;
        form.target = '_blank';
        document.body.appendChild(form);

        this.#createHiddenInput(form, 'token', token);
        this.#createHiddenInput(form, 'customerId', customerId);
        this.#createHiddenInput(form, 'userId', userId);

        form.submit();
        form.remove();
    }

    #createHiddenInput(form: HTMLFormElement, name: string, value: string) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default StoreContextService;
