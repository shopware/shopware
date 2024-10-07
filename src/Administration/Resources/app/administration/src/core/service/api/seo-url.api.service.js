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

        return this.httpClient
            .patch(apiRoute, seoUrl, {
                params: additionalParams,
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    createCustomUrl(routeName, urls, additionalParams = {}, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/create-custom-url`;

        return this.httpClient
            .post(
                apiRoute,
                { routeName, urls },
                {
                    params: additionalParams,
                    headers: this.getBasicHeaders(additionalHeaders),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default SeoUrlApiService;
