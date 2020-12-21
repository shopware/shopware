const ApiService = Shopware.Classes.ApiService;

/**
 * Gateway for the API endpoint "promotion codes"
 * @class
 * @extends ApiService
 */
export default class PromotionCodeApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'promotion') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'promotionCodeApiService';
    }

    /**
     * @returns {Promise<T>}
     */
    generateCodeFixed() {
        const headers = this.getBasicHeaders();

        return this.httpClient.get(
            `/_action/${this.getApiBasePath()}/codes/generate-fixed`,
            {
                headers
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}
