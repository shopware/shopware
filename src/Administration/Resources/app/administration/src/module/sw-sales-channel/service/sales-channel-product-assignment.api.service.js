/**
 * @sw-package discovery
 *
 * @private
 */

const { Application } = Shopware;
const ApiService = Shopware.Classes.ApiService;

/**
 * Every request assigns thousands of products, so this cap is only reached when the catalog changes
 * faster than the assignment proceeds. It keeps the loop finite instead of waiting for the server to agree.
 */
const MAX_REQUESTS = 200;

class SalesChannelProductAssignmentApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'sales-channel') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'salesChannelProductAssignmentApiService';
    }

    /**
     * Resolves the given sources on the server and makes the matching products visible in the sales channel.
     * Resolves with the number of newly assigned products.
     */
    async assign(salesChannelId, { productIds = [], productStreamIds = [], categoryIds = [], visibility = 30 } = {}) {
        let assigned = 0;

        for (let request = 0; request < MAX_REQUESTS; request += 1) {
            const result = await this.request(salesChannelId, { productIds, productStreamIds, categoryIds, visibility });
            assigned += result.assigned;

            if (result.complete) {
                return { assigned, complete: true };
            }
        }

        return { assigned, complete: false };
    }

    request(salesChannelId, payload) {
        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/${salesChannelId}/product-assignment`, payload, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
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
