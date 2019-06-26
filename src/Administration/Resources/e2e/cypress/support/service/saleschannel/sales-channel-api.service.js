const ApiService = require('../api.service');

export default class SalesChannelApiService extends ApiService {
    constructor() {
        super();
        this.accessKey = '';
        this.contextToken = '';
    }

    getBasicPath() {
        return `${Cypress.config('baseUrl')}/sales-channel-api`;
    }

    /**
     * Returns the necessary headers for the API requests
     *
     * @returns {Object}
     */
    getHeaders() {
        return {
            'Content-Type': 'application/json',
            'SW-Access-Key': `${this.accessKey}`,
            'SW-Context-Token': `${this.contextToken}`
        };
    }

    request({ url, method, params, data }) {
        const requestConfig = {
            headers: this.getHeaders(),
            url,
            method,
            params,
            data
        };

        return this.client.request(requestConfig).then((response) => {
            if (Array.isArray(response.data.data) && response.data.data.length === 1) {
                return response.data[0];
            }
            return response.data;
        }).catch(({ response }) => {
            if (response.data && response.data.errors) {
                console.log(response.data.errors);
            }
        });
    }

    setAccessKey(salesChannelId) {
        this.accessKey = salesChannelId;
    }

    setContextToken(contextId) {
        this.contextToken = contextId;
    }
}
