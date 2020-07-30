// eslint-disable-next-line max-len
const AdminApiService = require('@shopware-ag/e2e-testsuite-platform/cypress/support/service/administration/admin-api.service');

class RuleBuilderFixture {
    constructor() {
        this.apiClient = new AdminApiService();
    }

    create(endpoint, rawData) {
        return this.apiClient.post(`/v3/${endpoint}?response=true`, rawData);
    }

    update(userData) {
        if (!userData.id) {
            throw new Error('Update fixtures must always contain an id');
        }
        return this.apiClient.patch(`/v3/${userData.type}/${userData.id}`, userData.data);
    }

    search(type, filter) {
        return this.apiClient.post(`/v3/search/${type}?response=true`, {
            filter: [{
                field: filter.field ? filter.field : 'name',
                type: 'equals',
                value: filter.value
            }]
        });
    }

    setRuleFixture(userData, shippingMethodName) {
        // Create rule fixture via api
        return this.create('rule', userData).then(() => {
            return this.search('rule', {
                field: 'name',
                value: 'Foobar'
            })
        }).then((ruleData) => {
            const ruleId = ruleData.id;
            // Get the shipping method id
            return this.search('shipping-method', {
                field: 'name',
                value: shippingMethodName
            }).then((shippingMethodData) => {
                const shippingMethodId = shippingMethodData.id;

                return this.update({
                    type: 'shipping-method',
                    id: shippingMethodId,
                    data: {
                        availabilityRuleId: ruleId
                    }
                });
            });
        });
    }
}

module.exports = RuleBuilderFixture;
