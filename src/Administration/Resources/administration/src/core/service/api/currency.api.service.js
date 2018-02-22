import ApiService from './api.service';

/**
 * Gateway for the API end point "currency"
 * @class
 * @extends ApiService
 */
class CurrencyApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'currency') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default CurrencyApiService;
