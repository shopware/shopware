import ApiService from '../api.service';

/**
 * @class
 * @extends ApiService
 */
class LanguageApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'language') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'languageApiService';
    }

    changeDefaultCurrency(currencyId, additionalParams = {}, additionalHeaders = {}) {
        const route = `_action/currency/change-default-currency/${currencyId}`;

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(route, {}, {
                additionalParams,
                headers
            });
    }
}

export default LanguageApiService;
