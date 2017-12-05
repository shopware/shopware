import ApiService from './api.service';

class TaxApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'tax', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default TaxApiService;
