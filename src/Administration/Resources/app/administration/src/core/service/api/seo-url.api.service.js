import ApiService from '../api.service';

/**
 * Gateway for the API end point "seo-url"
 * @class
 * @extends ApiService
 */
class SeoUrlApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'seo-url') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'seoUrlService';
    }

    updateCanonicalUrl(seoUrl, languageId, additionalParams = {}, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/canonical`;

        Object.assign(additionalHeaders, { 'sw-language-id': languageId });

        return this.httpClient.patch(
            apiRoute,
            seoUrl,
            {
                params: additionalParams,
                headers: this.getBasicHeaders(additionalHeaders)
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

export default SeoUrlApiService;
