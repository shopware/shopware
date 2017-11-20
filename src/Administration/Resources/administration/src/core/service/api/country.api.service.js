import ApiService from './api.service';

class CountryApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'country', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default CountryApiService;
