/**
 * @package system-settings
 */
import ApiService from '../api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class ExcludedSearchTermService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'reset-excluded-search-term') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'excludedSearchTermService';
    }

    resetExcludedSearchTerm() {
        const route = '/_admin/reset-excluded-search-term';
        const headers = this._getHeader();
        return this.httpClient.post(route, {}, { headers });
    }

    _getHeader() {
        return {
            ...super.getBasicHeaders(),
            'sw-language-id': Shopware.Context.api.languageId,
        };
    }
}
