import ApiService from './api.service';

class CountryApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'country', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default CountryApiService;
