import ApiService from './api.service';

/**
 * Gateway for the API end point "sales-channel"
 * @class
 * @extends ApiService
 */
class SalesChannelTypeApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'sales-channel-type') {
        super(httpClient, loginService, apiEndpoint);
    }
}

export default SalesChannelTypeApiService;
