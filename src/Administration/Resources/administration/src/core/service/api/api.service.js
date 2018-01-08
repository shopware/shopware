/* eslint-disable */

class ApiService {
    constructor(httpClient, loginService, apiEndpoint, returnFormat = 'json') {
        this.httpClient = httpClient;
        this.httpClient.defaults.headers.common.Authorization = `Bearer ${loginService.getToken()}`;

        this.apiEndpoint = apiEndpoint;
        // this.returnFormat = returnFormat;
        this.returnFormat = '';
    }

    getList(offset = 0, limit = 25) {
        return this.httpClient
            .get(`${this.getApiBasePath()}?offset=${offset}&limit=${limit}`)
            .then((response) => {
                return response.data;
            });
    }

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

    updateById(id, payload) {
        if (!id) {
            return Promise.reject(new Error('Missing required argument: id'));
        }

        return this.httpClient
            .patch(this.getApiBasePath(id), payload)
            .then((response) => {
                return response.data;
            });
    }

    create(payload) {
        return this.httpClient
            .post(this.getApiBasePath(), payload)
            .then((response) => {
                return response.data;
            });
    }

    getApiBasePath(id) {
        const returnFormat = (this.returnFormat.length) ? `.${this.returnFormat}` : '';

        if (id && id.length > 0) {
            return `${this.apiEndpoint}/${id}${returnFormat}`;
        }

        return `${this.apiEndpoint}${returnFormat}`;
    }

    get apiEndpoint() {
        return this.endpoint;
    }

    set apiEndpoint(endpoint) {
        this.endpoint = endpoint;
    }

    get httpClient() {
        return this.client;
    }

    set httpClient(client) {
        this.client = client;
    }

    get returnFormat() {
        return this.format;
    }

    set returnFormat(format) {
        this.format = format;
    }
}

export default ApiService;
