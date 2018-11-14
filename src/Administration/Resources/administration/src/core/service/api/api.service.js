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
     * Gets a list from the configured API end point using the page & limit.
     *
     * @param {Number} page
     * @param {Number} limit
     * @param {String} sortBy
     * @param {String} sortDirection
     * @param {String} term
     * @param {Object} criteria
     * @param {Object} aggregations
     * @param {Object} headers
     * @returns {Promise<T>}
     */
    getList({ page = 1, limit = 25, sortBy, sortDirection = 'asc', term, criteria, aggregations, headers }) {
        const requestHeaders = this.getBasicHeaders(headers);
        const params = { page, limit };

        if (sortBy && sortBy.length) {
            params.sort = (sortDirection.toLowerCase() === 'asc' ? '' : '-') + sortBy;
        }

        if (term) {
            params.term = term;
        }

        if (criteria) {
            params.filter = [criteria.getQuery()];
        }

        if (aggregations) {
            params.aggregations = aggregations;
        }

        // Switch to the general search end point when we're having a search term or aggregations
        if ((params.term && params.term.length) ||
                (params.filter && params.filter.length) ||
                (params.aggregations)) {
            return this.httpClient
                .post(`${this.getApiBasePath(null, 'search')}`, params, { headers: requestHeaders })
                .then((response) => {
                    return ApiService.handleResponse(response);
                });
        }

        return this.httpClient
            .get(this.getApiBasePath(), { params, headers: requestHeaders })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Get the detail entity from the API end point using the provided entity id.
     *
     * @param {String|Number} id
     * @param {Object} additionalParams
     * @param {Object} additionalHeaders
     * @returns {Promise<T>}
     */
    getById(id, additionalParams = {}, additionalHeaders = {}) {
        if (!id) {
            return Promise.reject(new Error('Missing required argument: id'));
        }

        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get(this.getApiBasePath(id), {
                params,
                headers
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Updates an entity using the provided payload.
     *
     * @param {String|Number} id
     * @param {Object} payload
     * @param {Object} additionalParams
     * @param {Object} additionalHeaders
     * @returns {Promise<T>}
     */
    updateById(id, payload, additionalParams = {}, additionalHeaders = {}) {
        if (!id) {
            return Promise.reject(new Error('Missing required argument: id'));
        }

        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .patch(this.getApiBasePath(id), payload, {
                params,
                headers
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Delete associations of the entity.
     *
     * @param id
     * @param associationKey
     * @param associationId
     * @param additionalHeaders
     * @returns {*}
     */
    deleteAssociation(id, associationKey, associationId, additionalHeaders) {
        if (!id || !associationId || !associationId) {
            return Promise.reject(new Error('Missing required arguments.'));
        }

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.delete(`${this.getApiBasePath(id)}/${associationKey}/${associationId}`, {
            headers
        }).then((response) => {
            if (response.status >= 200 && response.status < 300) {
                return Promise.resolve(response);
            }

            return Promise.reject(response);
        });
    }

    /**
     * Creates a new entity
     *
     * @param {any} payload
     * @param {Object} additionalParams
     * @param {Object} additionalHeaders
     * @returns {Promise<T>}
     */
    create(payload, additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(this.getApiBasePath(), payload, {
                params,
                headers
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Deletes an existing entity
     *
     * @param {Number} id
     * @param {Object} [additionalParams={}]
     * @param {Object} [additionalHeaders={}]
     * @returns {Promise<T>}
     */
    delete(id, additionalParams = {}, additionalHeaders = {}) {
        if (!id) {
            return Promise.reject(new Error('Missing required argument: id'));
        }

        const params = Object.assign({}, additionalParams);
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .delete(this.getApiBasePath(id), {
                params,
                headers
            });
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

        if (prefix && prefix.length) {
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
            'Content-Type': 'application/json'
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
        if (!response.data) {
            return response;
        }

        let data = response.data;
        const headers = response.headers;

        if (headers && headers['content-type'] && headers['content-type'] === 'application/vnd.api+json') {
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
