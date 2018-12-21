const ApiService = require('./../../../common/service/api.service');

export default class AdminApiService extends ApiService {
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
        return `${path}/api`;
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

    request({url, method, params, data}) {
        return super.request({url, method, params, data}).catch(({ config, response }) => {
            console.log('###############################');
            console.log(response.data);
        });
    }
}
