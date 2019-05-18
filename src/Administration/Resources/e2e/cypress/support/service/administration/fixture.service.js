const _ = require('lodash');
const uuid = require('uuid/v4');
const AdminApiService = require('./admin-api.service');

export default class AdminFixtureService {
    constructor() {
        this.apiClient = new AdminApiService(process.env.APP_URL);
        this.basicFixture = '';
    }

    setBasicFixture(json) {
        this.basicFixture = this.loadJson(json);
    }

    create(endpoint, rawData) {
        return this.apiClient.post(`/v1/${endpoint}?response=true`, rawData);
    }

    update(userData) {
        if (userData.id) {
            return this.apiClient.patch(`/v1/${userData.type}/${userData.id}?_response=true`, userData.data);
        }

        return this.search(userData.type, userData.data).then((result) => {
            this.apiClient.patch(`/v1/${userData.type}/${result.id}?_response=true`, userData.data);
        });
    }

    search(type, filter) {
        return this.apiClient.post(`/v1/search/${type}?response=true`, {
            filter: [{
                field: filter.field ? filter.field : 'name',
                type: 'equals',
                value: filter.value
            }]
        });
    }

    createUuid() {
        return uuid();
    }

    mergeFixtureWithData(...args) {
        const result = _.merge({}, ...args);
        return result;
    }
}

global.AdminFixtureService = new AdminFixtureService();
