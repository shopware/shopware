const ApiService = require('../../administration/service/api.service');

/* This service is taken over one by one from administration repository in order to provide a starting point.
   Please adjust it to the storefront sooner or later. */

export default class StorefrontApiService extends ApiService {

    getClientId(salesChannelName = 'Storefront API', url = '/v1/search/sales-channel?response=true') {
        return this.post(url, {
            filter: [{
                field: "name",
                type: "equals",
                value: salesChannelName,
            }]
        }).then((result) => {
            return result.attributes.accessKey;
        })
    }

    getBasicPath(path) {
        return `${path}/api`;
    }

    /**
     * Returns the necessary headers for the API requests
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
        return super.request({url, method, params, data}).catch(({config, response}) => {
            if (response.data && response.data.errors) {
                console.log(response.data.errors);
            }
        });
    }
}
