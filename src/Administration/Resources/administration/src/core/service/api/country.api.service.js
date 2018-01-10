import ApiService from './api.service';

/**
 * Gateway for the API end point "country"
 * @class
 * @extends ApiService
 */
class CountryApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'country', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default CountryApiService;
