import ApiService from './api.service';

/**
 * Gateway for the API end point "order-delivery"
 * @class
 * @extends ApiService
 */
class OrderDeliveryApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'order-delivery') {
        super(httpClient, loginService, apiEndpoint);
    }

    /**
     * Gets a list from the configured API end point using the offset & limit.
     *
     * @param {Number} [offset=0]
     * @param {Number} [limit=25]
     * @param {Number} orderId
     * @returns {Promise<T>}
     */
    getList(offset = 0, limit = 25, orderId) {
        if (!orderId) {
            return Promise.reject(new Error('Missing required argument: orderId'));
        }

        const queryString = JSON.stringify({
            type: 'nested',
            queries: [
                {
                    type: 'term',
                    field: 'order_delivery.orderId',
                    value: orderId
                }
            ]
        });

        return this.httpClient
            .get(`${this.getApiBasePath()}?offset=${offset}&limit=${limit}&query=${queryString}`)
            .then((response) => {
                return response.data;
            });
    }
}

export default OrderDeliveryApiService;
