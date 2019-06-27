const _ = require('lodash');
const uuid = require('uuid/v4');
const SalesChannelApiService = require('./sales-channel-api.service');
const AdminApiService = require('../administration/admin-api.service');
const AdminFixtureService = require('../administration/fixture.service.js');

export default class SalesChannelFixtureService {
    constructor() {
        this.apiClient = new SalesChannelApiService(process.env.APP_URL);
        this.adminApiClient = new AdminApiService(process.env.APP_URL);
    }

    createUuid() {
        return uuid();
    }

    mergeFixtureWithData(...args) {
        const result = _.merge({}, ...args);
        return result;
    }

    search(type, filter) {
        const adminFixtures = new AdminFixtureService();
        return adminFixtures.search(type, filter).then((result) => {
            return result;
        });
    }

    getClientId(salesChannelName = 'Storefront') {
        return this.adminApiClient.post('/v1/search/sales-channel?response=true', {
            filter: [{
                field: 'name',
                type: 'equals',
                value: salesChannelName
            }]
        }).then((result) => {
            return result.attributes.accessKey;
        });
    }
}

global.SalesChannelFixtureService = new SalesChannelFixtureService();
