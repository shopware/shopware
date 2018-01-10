import ApiService from './api.service';

/**
 * Gateway for the API end point "payment-method"
 * @class
 * @extends ApiService
 */
class PaymentMethodApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'payment-method', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default PaymentMethodApiService;
