import ApiService from './api.service';

/**
 * Gateway for the API end point "currency"
 * @class
 * @extends ApiService
 */
class CurrencyApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'currency', returnFormat = 'json') {
        super(httpClient, loginService, apiEndpoint, returnFormat);
    }
}

export default CurrencyApiService;
