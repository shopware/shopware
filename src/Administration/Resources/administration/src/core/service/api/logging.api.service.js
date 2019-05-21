import ApiService from '../api.service';

/**
 * Gateway for the API end point "document"
 * @class
 * @extends ApiService
 */
class LoggingApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService);
        this.name = 'loggingService';
    }

    getLogs(additionalParams = {}, additionalHeaders = {}) {
        const route = '/_action/logs';
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get(route, {
                additionalParams,
                headers
            });
    }

    search(searchTerm, additionalParams = {}, additionalHeaders = {}) {
        const route = '/_action/logs/search';
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(route, { searchTerm: searchTerm }, {
                additionalParams,
                headers
            });
    }
}

export default LoggingApiService;
