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
                headers,
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    /**
     * @param {String} codePattern
     * @param {Number} amount
     *
     * @returns {Promise<T>}
     */
    generateIndividualCodes(codePattern, amount = 1) {
        const headers = this.getBasicHeaders();

        return this.httpClient.get(
            `/_action/${this.getApiBasePath()}/codes/generate-individual`,
            {
                params: { codePattern, amount },
                headers,
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    /**
     * @param {String} promotionId
     * @param {Number} amount
     *
     * @returns {Promise<T>}
     */
    addIndividualCodes(promotionId, amount) {
        const headers = this.getBasicHeaders();

        return this.httpClient.post(
            `/_action/${this.getApiBasePath()}/codes/add-individual`,
            {
                promotionId,
                amount,
            },
            {
                headers,
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    /**
     * @param {String} promotionId
     * @param {String} codePattern
     * @param {Number} amount
     *
     * @returns {Promise<T>}
     */
    replaceIndividualCodes(promotionId, codePattern, amount = 1) {
        const headers = this.getBasicHeaders();

        return this.httpClient.patch(
            `/_action/${this.getApiBasePath()}/codes/replace-individual`,
            {
                promotionId,
                codePattern,
                amount,
            },
            {
                headers,
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    /**
     * @param {String} codePattern
     *
     * @returns {Promise<T>}
     */
    generatePreview(codePattern) {
        const headers = this.getBasicHeaders();

        return this.httpClient.get(
            `/_action/${this.getApiBasePath()}/codes/preview`,
            {
                params: { codePattern },
                headers,
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}
