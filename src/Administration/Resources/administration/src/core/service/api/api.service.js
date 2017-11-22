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
     * @param {String} [returnFormat=json]
     */
    constructor(httpClient, loginService, apiEndpoint, returnFormat = 'json') {
        this.httpClient = httpClient;
        this.httpClient.defaults.headers.common.Authorization = `Bearer ${loginService.getToken()}`;

        this.apiEndpoint = apiEndpoint;
        this.returnFormat = returnFormat;

        // TODO - Use return format
        this.returnFormat = '';
    }

    /**
     * Gets a list from the configured API end point using the offset & limit.
     *
     * @param {Number} [offset=0]
     * @param {Number} [limit=25]
     * @returns {Promise<T>}
     */
    getList(offset = 0, limit = 25) {
        return this.httpClient
            .get(`${this.getApiBasePath()}?offset=${offset}&limit=${limit}`)
            .then((response) => {
                return response.data;
            });
    }

    /**
     * Get the detail entity from the API end point using the provided entity id.
     *
     * @param {String|Number} id
     * @returns {Promise<T>}
     */
    getById(id) {
        if (!id) {
            return Promise.reject(new Error('Missing required argument: id'));
        }

        return this.httpClient
            .get(this.getApiBasePath(id))
            .then((response) => {
                return response.data;
            });
    }

    /**
     * Updates an entity using the provided payload.
     *
     * @param {String|Number} id
     * @param {any} payload
     * @returns {Promise<T>}
     */
    updateById(id, payload) {
        if (!id) {
            return Promise.reject(new Error('Missing required argument: id'));
        }

        return this.httpClient
            .patch(`${this.getApiBasePath(id)}?_response=detail`, payload)
            .then((response) => {
                return response.data;
            });
    }

    /**
     * Creates a new entity
     *
     * @param {any} payload
     * @returns {Promise<T>}
     */
    create(payload) {
        return this.httpClient
            .post(`${this.getApiBasePath()}?_response=detail`, payload)
            .then((response) => {
                return response.data;
            });
    }

    /**
     * Returns the URI to the API endpoint
     *
     * @param {String|Number} [id]
     * @returns {String}
     */
    getApiBasePath(id) {
        const returnFormat = (this.returnFormat.length) ? `.${this.returnFormat}` : '';

        if (id && id.length > 0) {
            return `${this.apiEndpoint}/${id}${returnFormat}`;
        }

        return `${this.apiEndpoint}${returnFormat}`;
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
     * Getter & setter for the http client
     *
     * @type {String}
     */
    get returnFormat() {
        return this.format;
    }

    /**
     * @type {String}
     */
    set returnFormat(format) {
        this.format = format;
    }
}

export default ApiService;
