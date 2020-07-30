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
     * @deprecated tag:v6.4.0 - use src/core/data-new/repository.data.js search() function instead
     *
     * Gets a list from the configured API end point using the page & limit.
     *
     * @param {Number} page
     * @param {Number} limit
     * @param {String} sortBy
     * @param {String} sortDirection
     * @param {String} term
     * @param {Array} queries
     * @param {Array} sortings
     * @param {Object} criteria
     * @param {Object} aggregations
     * @param {Object} associations
     * @param {Object} headers
     * @param {String} versionId
     * @param {Array} ids
     * @param {Number} total-count-mode
     * @returns {Promise<T>}
     */
    getList({
        page = 1,
        limit = 25,
        sortBy, sortDirection = 'asc',
        sortings,
        queries, term,
        criteria,
        aggregations,
        associations,
        headers,
        versionId,
        ids,
        'total-count-mode': totalCountMode = 0
    }) {
        this.showDeprecationWarning('getList');

        let requestHeaders = this.getBasicHeaders(headers);
        const params = { page, limit };

        if (sortings) {
            params.sort = sortings;
        } else if (sortBy && sortBy.length) {
            params.sort = (sortDirection.toLowerCase() === 'asc' ? '' : '-') + sortBy;
        }

        if (ids) {
            params.ids = ids.join('|');
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

        if (associations) {
            params.associations = associations;
        }

        if (versionId) {
            requestHeaders = Object.assign(requestHeaders, ApiService.getVersionHeader(versionId));
        }

        if (queries) {
            params.query = queries;
        }

        if (totalCountMode) {
            params['total-count-mode'] = totalCountMode;
        }

        // Switch to the general search end point when we're having a search term or aggregations
        if ((params.term && params.term.length) ||
                (params.filter && params.filter.length) ||
                (params.aggregations) ||
                (params.sort) ||
                (params.queries) ||
                (params.associations)) {
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
     * @deprecated tag:v6.4.0 - use src/core/data-new/repository.data.js get() function instead
     *
     * Get the detail entity from the API end point using the provided entity id.
     *
     * @param {String|Number} id
     * @param {Object} additionalParams
     * @param {Object} additionalHeaders
     * @returns {Promise<T>}
     */
    getById(id, additionalParams = {}, additionalHeaders = {}) {
        this.showDeprecationWarning('getById');

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
     * @deprecated tag:v6.4.0 - use src/core/data-new/repository.data.js save() function instead
     *
     * Updates an entity using the provided payload.
     *
     * @param {String|Number} id
     * @param {Object} payload
     * @param {Object} additionalParams
     * @param {Object} additionalHeaders
     * @returns {Promise<T>}
     */
    updateById(id, payload, additionalParams = {}, additionalHeaders = {}) {
        this.showDeprecationWarning('updateById');

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
     * @deprecated tag:v6.4.0 - use src/core/data-new/repository.data.js delete() function instead
     *
     * Delete associations of the entity.
     *
     * @param id
     * @param associationKey
     * @param associationId
     * @param additionalHeaders
     * @returns {*}
     */
    deleteAssociation(id, associationKey, associationId, additionalHeaders) {
        this.showDeprecationWarning('deleteAssociation');

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
     * @deprecated tag:v6.4.0 - use src/core/data-new/repository.data.js create() function instead
     *
     * Creates a new entity
     *
     * @param {any} payload
     * @param {Object} additionalParams
     * @param {Object} additionalHeaders
     * @returns {Promise<T>}
     */
    create(payload, additionalParams = {}, additionalHeaders = {}) {
        this.showDeprecationWarning('create');

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
     * @deprecated tag:v6.4.0 - use src/core/data-new/repository.data.js delete() function instead
     *
     * Deletes an existing entity
     *
     * @param {Number} id
     * @param {Object} [additionalParams={}]
     * @param {Object} [additionalHeaders={}]
     * @returns {Promise<T>}
     */
    delete(id, additionalParams = {}, additionalHeaders = {}) {
        this.showDeprecationWarning('delete');

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
     *
     * @deprecated tag:v6.4.0 - use src/core/data-new/repository.data.js clone() function instead
     *
     * Clones an existing entity
     *
     * @param {Number} id
     * @returns {Promise<T>}
     */
    clone(id) {
        this.showDeprecationWarning('clone');

        if (!id) {
            return Promise.reject(new Error('Missing required argument: id'));
        }

        return this.httpClient
            .post(`/_action/clone/${this.apiEndpoint}/${id}`, null, {
                headers: this.getBasicHeaders()
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * @deprecated tag:v6.4.0 - use src/core/data-new/repository.data.js createVersion() function instead
     */
    versionize(id, additionalParams = {}, additionalHeaders = {}) {
        this.showDeprecationWarning('versionize');

        // todo fix route
        const route = `/_action/version/${this.apiEndpoint}/${id}`;

        const params = Object.assign({}, additionalParams);
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(route, {}, {
                params,
                headers
            });
    }

    /**
     * @deprecated tag:v6.4.0 - use src/core/data-new/repository.data.js mergeVersion() function instead
     */
    mergeVersion(id, versionId, additionalParams, additionalHeaders) {
        this.showDeprecationWarning('mergeVersion');

        if (!id) {
            return Promise.reject(new Error('Missing required argument: id'));
        }
        if (!versionId) {
            return Promise.reject(new Error('Missing required argument: versionId'));
        }

        const params = Object.assign({}, additionalParams);
        const headers = Object.assign(ApiService.getVersionHeader(versionId), this.getBasicHeaders(additionalHeaders));

        const route = `_action/version/merge/${this.apiEndpoint}/${versionId}`;
        return this.httpClient
            .post(route, {}, {
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

    showDeprecationWarning(functionName) {
        Shopware.Utils.debug.warn(
            `${this.apiEndpoint} - Api Service`,
            // eslint-disable-next-line max-len
            `The ${functionName} function is deprecated. Please use the 'repository.data.js' class for data handling of entities.`
        );
    }

    getApiVersion() {
        return Shopware.Context.api.apiVersion - 1;
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
