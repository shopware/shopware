import ApiService from './api.service';

class CurrencyApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'currency', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default CurrencyApiService;
