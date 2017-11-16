class ApiService {
    constructor(httpClient, apiEndpoint, returnFormat = 'json') {
        this.httpClient = httpClient;
        this.apiEndpoint = apiEndpoint;
        this.returnFormat = returnFormat;
    }

    getList(offset = 0, limit = 25) {
        return this.httpClient
            .get(`${this.getApiBasePath()}?offset=${offset}&limit=${limit}`)
            .then((response) => {
                return response.data;
            });
    }

    getByUuid(uuid) {
        if (!uuid) {
            return Promise.reject(new Error('Missing required argument: uuid'));
        }

        return this.httpClient
            .get(this.getApiBasePath(uuid))
            .then((response) => {
                return response.data;
            });
    }

    updateByUuid(uuid, payload) {
        if (!uuid) {
            return Promise.reject(new Error('Missing required argument: uuid'));
        }

        return this.httpClient
            .patch(this.getApiBasePath(uuid), payload)
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

    getApiBasePath(uuid) {
        const returnFormat = (this.returnFormat.length) ? `.${this.returnFormat}` : '';

        if (uuid && uuid.length > 0) {
            return `${this.apiEndpoint}/${uuid})${returnFormat}`;
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
