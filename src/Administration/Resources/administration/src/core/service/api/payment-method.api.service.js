import ApiService from './api.service';

class PaymentMethodApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'payment-method', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default PaymentMethodApiService;
