const ApiService = Shopware.Classes.ApiService;

/**
 * Gateway for the API end point "theme"
 * @class
 * @extends ApiService
 */
class ThemeApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'theme') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'themeService';
    }

    assignTheme(themeId, salesChannelId, additionalParams = {}, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/${themeId}/assign/${salesChannelId}`;

        return this.httpClient.post(
            apiRoute,
            {},
            {
                params: { ...additionalParams },
                headers: this.getBasicHeaders(additionalHeaders)
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    updateTheme(themeId, data, additionalParams = {}, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/${themeId}`;

        return this.httpClient.patch(
            apiRoute,
            data,
            {
                params: { ...additionalParams },
                headers: this.getBasicHeaders(additionalHeaders)
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    resetTheme(themeId, additionalParams = {}, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/${themeId}/reset`;

        return this.httpClient.patch(
            apiRoute,
            {},
            {
                params: { ...additionalParams },
                headers: this.getBasicHeaders(additionalHeaders)
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    getConfiguration(themeId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/${themeId}/configuration`;

        const additionalHeaders = {};

        return this.httpClient.get(
            apiRoute,
            {
                headers: this.getBasicHeaders(additionalHeaders)
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    /**
     * @deprecated tag:v6.4.0 - use getStructuredFields instead
     * @param themeId
     * @returns {Promise<AxiosResponse<T>>}
     */
    getFields(themeId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/${themeId}/fields`;

        const additionalHeaders = {};

        return this.httpClient.get(
            apiRoute,
            {
                headers: this.getBasicHeaders(additionalHeaders)
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    getStructuredFields(themeId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/${themeId}/structured-fields`;

        const additionalHeaders = {};

        return this.httpClient.get(
            apiRoute,
            {
                headers: this.getBasicHeaders(additionalHeaders)
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

export default ThemeApiService;
