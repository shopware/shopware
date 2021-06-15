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
}

export default CalculatePriceApiService;
