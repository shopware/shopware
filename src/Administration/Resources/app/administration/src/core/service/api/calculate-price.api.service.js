/**
 * @package checkout
 */
import ApiService from '../api.service';

/**
 * Gateway for the API end point "calculate-price"
 * @class
 * @extends ApiService
 */
class CalculatePriceApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'calculate-price') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'calculate-price';
    }

    calculatePrice({ taxId, price, output = 'gross', currencyId }, additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);
        const payload = {
            taxId,
            price,
            output,
            currencyId,
        };

        return this.httpClient
            .post(`/_action/${this.apiEndpoint}`, payload, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    calculatePrices(taxId, prices, additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);
        const payload = {
            taxId,
            prices,
        };

        return this.httpClient
            .post('/_action/calculate-prices', payload, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response.data);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default CalculatePriceApiService;
