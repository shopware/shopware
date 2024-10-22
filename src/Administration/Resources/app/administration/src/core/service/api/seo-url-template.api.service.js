import ApiService from '../api.service';

/**
 * Gateway for the API end point "seo-url-template"
 * @class
 * @extends ApiService
 */
class SeoUrlTemplateApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'seo-url-template') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'seoUrlTemplateService';
    }

    validate(urlTemplate, additionalParams = {}, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/validate`;

        return this.httpClient
            .post(apiRoute, urlTemplate, {
                params: additionalParams,
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    preview(urlTemplate, additionalParams = {}, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/preview`;

        return this.httpClient
            .post(apiRoute, urlTemplate, {
                params: additionalParams,
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => {
                if (response.status === 204) {
                    return null;
                }
                return ApiService.handleResponse(response);
            });
    }

    getContext(urlTemplate, additionalParams = {}, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/context`;

        return this.httpClient
            .post(apiRoute, urlTemplate, {
                params: additionalParams,
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getDefault(route, additionalParams = {}, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/default/${route}`;

        return this.httpClient
            .get(apiRoute, {
                params: additionalParams,
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default SeoUrlTemplateApiService;
