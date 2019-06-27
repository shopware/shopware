/* eslint-disable no-unused-vars */

const ApiService = require('../api.service');

export default class AdminApiService extends ApiService {
    getBasicPath() {
        return `${Cypress.config('baseUrl')}/api`;
    }

    /**
     * Renders an header to stdout including information about the available flags.
     *
     * @param {String} username
     * @param {String} password
     * @returns {Object}
     */
    loginByUserName(username = 'admin', password = 'shopware') {
        return this.client.post('/oauth/token', {
            grant_type: 'password',
            client_id: 'administration',
            scopes: 'write',
            username: username,
            password: password
        }).catch((err) => {
            console.log(Promise.reject(err.data));
        }).then((response) => {
            this.authInformation = response.data;
            return this.authInformation;
        });
    }

    /**
     * Returns the necessary headers for the administration API requests
     *
     * @returns {Object}
     */
    getHeaders() {
        return {
            Accept: 'application/vnd.api+json',
            Authorization: `Bearer ${this.authInformation.access_token}`,
            'Content-Type': 'application/json'
        };
    }

    request({ url, method, params, data }) {
        return this.loginByUserName().then(() => {
            const requestConfig = {
                headers: this.getHeaders(),
                url,
                method,
                params,
                data
            };

            return this.client.request(requestConfig).then((response) => {
                if (Array.isArray(response.data.data) && response.data.data.length === 1) {
                    return response.data.data[0];
                }
                return response.data.data;
            });
        }).catch(({ response }) => {
            if (response.data && response.data.errors) {
                console.log(response.data.errors);
            }
        });
    }

    clearCache() {
        return super.clearCache('/v1/_action/cache');
    }

    loginToAdministration() {
        return this.loginByUserName().then((responseData) => {
            return {
                access: responseData.access_token,
                refresh: responseData.refresh_token,
                expiry: Math.round(+new Date() / 1000) + responseData.expires_in
            };
        }).catch(({ config, response }) => {
            if (response.data && response.data.errors) {
                console.log(response.data.errors);
            }
        });
    }
}
