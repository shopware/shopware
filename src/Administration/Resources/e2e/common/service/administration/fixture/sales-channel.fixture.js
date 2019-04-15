const AdminFixtureService = require('./../fixture.service.js').default;

export default class AdminSalesChannelFixtureService extends AdminFixtureService {
    constructor() {
        super();

        this.salesChannelFixture = this.loadJson('sales-channel.json');
    }

    setSalesChannelBasicFixture(json) {
        this.salesChannelFixture = json;
    }

    setSalesChannelFixture(userData) {
        const startTime = new Date();
        global.logger.title('Set sales channel fixtures...');

        const jsonData = this.salesChannelFixture;

        let currencyId = '';
        let paymentMethodId = '';
        let shippingMethodId = '';
        let countryId = '';
        let languageId = '';
        let salesChannelTypeId = '';
        let customerGroupId = '';

        return this.apiClient.post('/v1/search/shipping-method?response=true', {
            filter: [{
                field: 'name',
                type: 'equals',
                value: 'Standard'
            }]
        }).then((shippingMethod) => {
            shippingMethodId = shippingMethod.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/currency?response=true', {
                filter: [{
                    field: 'name',
                    type: 'equals',
                    value: 'Euro'
                }]
            });
        }).then((currency) => {
            currencyId = currency.id;
        })
            .then(() => {
                return this.apiClient.post('/v1/search/payment-method?response=true', {
                    filter: [{
                        field: 'name',
                        type: 'equals',
                        value: 'Invoice'
                    }]
                });
            })
            .then((paymentMethod) => {
                paymentMethodId = paymentMethod.id;
            })
            .then(() => {
                return this.apiClient.post('/v1/search/country?response=true', {
                    filter: [{
                        field: 'iso',
                        type: 'equals',
                        value: 'DE'
                    }]
                });
            })
            .then((country) => {
                countryId = country.id;
            })
            .then(() => {
                return this.apiClient.post('/v1/search/sales-channel-type?response=true', {
                    filter: [{
                        field: 'name',
                        type: 'equals',
                        value: 'Storefront'
                    }]
                });
            })
            .then((type) => {
                salesChannelTypeId = type.id;
            })
            .then(() => {
                return this.apiClient.post('/v1/search/language?response=true', {
                    filter: [{
                        field: 'name',
                        type: 'equals',
                        value: 'Deutsch'
                    }]
                });
            })
            .then((language) => {
                languageId = language.id;
            })
            .then(() => {
                return this.apiClient.post('/v1/search/customer-group?response=true', {
                    filter: [{
                        field: 'name',
                        type: 'equals',
                        value: 'Standard customer group'
                    }]
                });
            })
            .then((customerGroup) => {
                customerGroupId = customerGroup.id;
            })
            .then(() => {
                return this.mergeFixtureWithData(jsonData, {
                    currencyId: currencyId,
                    paymentMethodId: paymentMethodId,
                    shippingMethodId: shippingMethodId,
                    countryId: countryId,
                    languageId: languageId,
                    typeId: salesChannelTypeId,
                    customerGroupId: customerGroupId
                }, userData);
            })
            .then((finalChannelData) => {
                return this.apiClient.post('/v1/sales-channel?_response=true', finalChannelData);
            })
            .then((data) => {
                const endTime = new Date() - startTime;
                global.logger.success(`${data.id} (${endTime / 1000}s)`);
                global.logger.lineBreak();
            })
            .catch((err) => {
                global.logger.error(err);
                global.logger.lineBreak();
            });
    }
}

global.AdminSalesChannelFixtureService = new AdminSalesChannelFixtureService();
