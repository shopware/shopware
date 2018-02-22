import ApiService from './api.service';

/**
 * Gateway for the API end point "country"
 * @class
 * @extends ApiService
 */
class CountryApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'country') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default CountryApiService;
