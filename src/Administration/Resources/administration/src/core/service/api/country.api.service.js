import ApiService from './api.service';

class CountryApiService extends ApiService {
    constructor(httpClient, apiEndpoint = 'areaCountry', returnFormat = 'json') {
        super(httpClient, apiEndpoint, returnFormat);
    }
}

export default CountryApiService;
