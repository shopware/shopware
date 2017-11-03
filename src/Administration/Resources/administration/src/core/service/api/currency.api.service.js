import ApiService from './api.service';

class CurrencyApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'currency', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default CurrencyApiService;
