import ApiService from './api.service';

class PaymentMethodApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'payment-method', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default PaymentMethodApiService;
