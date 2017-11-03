import ApiService from './api.service';

class TaxApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'tax', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default TaxApiService;
