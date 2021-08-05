import parseJsonApi from 'src/core/service/jsonapi-parser.service';

/**
 * ApiService class which provides the common methods for our REST API
 * @class
 */
class ApiService {
    /**
     * @constructor
     * @param {AxiosInstance} httpClient
     * @param {LoginService} loginService
     * @param {String} apiEndpoint
     * @param {String} [contentType='application/vnd.api+json']
     */
    constructor(httpClient, loginService, apiEndpoint, contentType = 'application/vnd.api+json') {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.apiEndpoint = apiEndpoint;
        this.contentType = contentType;
    }

    /**
     * Returns the URI to the API endpoint
     *
     * @param {String|Number} [id]
     * @param {String} [prefix='']
     * @returns {String}
     */
    getApiBasePath(id, prefix = '') {
        let url = '';

        if (prefix?.length) {
            url += `${prefix}/`;
        }

        if (id && id.length > 0) {
            return `${url}${this.apiEndpoint}/${id}`;
        }

        return `${url}${this.apiEndpoint}`;
    }

    /**
     * Get the basic headers for a request.
     *
     * @param additionalHeaders
     * @returns {Object}
     */
    getBasicHeaders(additionalHeaders = {}) {
        const basicHeaders = {
            Accept: this.contentType,
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };

        return Object.assign({}, basicHeaders, additionalHeaders);
    }

    /**
     * Basic response handling.
     * Converts the JSON api data when the specific content type is set.
     *
     * @param response
     * @returns {*}
     */
    static handleResponse(response) {
        if (response.data === null || response.data === undefined) {
            return response;
        }

        let data = response.data;
        const headers = response.headers;

        if (headers?.['content-type'] && headers['content-type'] === 'application/vnd.api+json') {
            data = ApiService.parseJsonApiData(data);
        }

        return data;
    }

    /**
     * Parses a JSON api data structure to a simplified object.
     *
     * @param data
     * @returns {Object}
     */
    static parseJsonApiData(data) {
        return parseJsonApi(data);
    }

    static getVersionHeader(versionId) {
        return { 'sw-version-id': versionId };
    }

    /**
     * Getter & setter for the API end point
     * @type {String}
     */
    get apiEndpoint() {
        return this.endpoint;
    }

    /**
     * @type {String}
     */
    set apiEndpoint(endpoint) {
        this.endpoint = endpoint;
    }

    /**
     * Getter & setter for the http client
     *
     * @type {AxiosInstance}
     */
    get httpClient() {
        return this.client;
    }

    /**
     * @type {AxiosInstance}
     */
    set httpClient(client) {
        this.client = client;
    }

    /**
     * @type {String}
     */
    get contentType() {
        return this.type;
    }

    /**
     * @type {String}
     */
    set contentType(contentType) {
        this.type = contentType;
    }
}

export default ApiService;
