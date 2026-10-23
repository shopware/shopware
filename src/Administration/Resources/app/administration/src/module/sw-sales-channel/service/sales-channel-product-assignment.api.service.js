/**
 * @sw-package discovery
 *
 * @private
 */

const { Application } = Shopware;
const ApiService = Shopware.Classes.ApiService;

class SalesChannelProductAssignmentApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'sales-channel') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'salesChannelProductAssignmentApiService';
    }

    /**
     * Resolves the given sources on the server and makes the matching products visible in the sales channel.
     * Resolves with the number of newly assigned products.
     */
    assign(salesChannelId, { productIds = [], productStreamIds = [], categoryIds = [], visibility = 30 } = {}) {
        return this.httpClient
            .post(
                `/_action/${this.getApiBasePath()}/${salesChannelId}/product-assignment`,
                { productIds, productStreamIds, categoryIds, visibility },
                { headers: this.getBasicHeaders() },
            )
            .then((response) => {
                return ApiService.handleResponse(response).assigned;
            });
    }
}

Application.addServiceProvider('salesChannelProductAssignmentApiService', () => {
    return new SalesChannelProductAssignmentApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default SalesChannelProductAssignmentApiService;
