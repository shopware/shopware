const ApiService = require('../api.service');

export default class SalesChannelApiService extends ApiService {
    loginByUserName(username = 'admin', password = 'shopware') {
        return this.client.post('/oauth/token', {
            grant_type: 'password',
            client_id: 'administration',
            scopes: 'write',
            username,
            password
        }).catch((err) => {
            console.log(Promise.reject(err.data));
        }).then((response) => {
            this.authInformation = response.data;
            return this.authInformation;
        });
    }

    getBasicPath(path) {
        return `${path}/sales-channel-api`;
    }

    /**
     * Returns the necessary headers for the API requests
     *
     * @returns {Object}
     */
    getHeaders() {
        return {
            'Content-Type': 'application/json',
            'X-SW-Access-Key': `${this.accessKey}`,
            'X-SW-Context-Token': `${this.contextToken}`
        };
    }

    request({url, method, params, data}) {
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
        }).catch(({config, response}) => {
            if (response.data && response.data.errors) {
                console.log(response.data.errors);
            }
        });
    }

    setAccessKey(salesChannelId) {
        this.accessKey = salesChannelId;
        return this.accessKey;
    }

    setContextToken(contextId) {
        this.contextToken = contextId;
        return this.contextToken;
    }
}
