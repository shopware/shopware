import ApiService from 'src/core/service/api.service';

/**
 * Gateway for the API end point "seo-url-template"
 * @class
 * @extends ApiService
 */
class SeoUrlTemplateApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'seo-url-template') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'SeoUrlTemplateService';
    }

    validate(urlTemplate, additionalParams = {}, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/validate`;

        return this.httpClient.post(
            apiRoute,
            urlTemplate,
            {
                params: additionalParams,
                headers: this.getBasicHeaders(additionalHeaders)
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    preview(urlTemplate, additionalParams = {}, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/preview`;

        return this.httpClient.post(
            apiRoute,
            urlTemplate,
            {
                params: additionalParams,
                headers: this.getBasicHeaders(additionalHeaders)
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    getContext(urlTemplate, additionalParams = {}, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/context`;

        return this.httpClient.post(
            apiRoute,
            urlTemplate,
            {
                params: additionalParams,
                headers: this.getBasicHeaders(additionalHeaders)
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

export default SeoUrlTemplateApiService;
