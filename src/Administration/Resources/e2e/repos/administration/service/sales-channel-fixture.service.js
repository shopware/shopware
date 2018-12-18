const FixtureService = require('administration/service/fixtures.service');

export default class SalesChannelFixtureService extends FixtureService {
    constructor() {
        super();
    }

    setSalesChannelFixture(jsonData, done) {
        console.log('### Set sales channel fixtures...');

        let currencyId = '';
        let paymentMethodId = '';
        let shippingMethodId = '';
        let countryId = '';
        let languageId = '';
        let salesChannelTypeId = '';

        return this.apiClient.post('/v1/search/shipping-method?response=true', {
            filter: [{
                field: "name",
                type: "equals",
                value: "Standard",
            }]
        }).then((shippingMethod) => {
            shippingMethodId = shippingMethod.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/currency?response=true', {
                filter: [{
                    field: "name",
                    type: "equals",
                    value: "Euro",
                }]
            });
        }).then((currency) => {
            currencyId = currency.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/payment-method?response=true', {
                filter: [{
                    field: "name",
                    type: "equals",
                    value: "Invoice",
                }]
            })
        }).then((paymentMethod) => {
            paymentMethodId = paymentMethod.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/country?response=true', {
                filter: [{
                    field: "iso",
                    type: "equals",
                    value: "DE",
                }]
            });
        }).then((country) => {
            countryId = country.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/sales-channel-type?response=true', {
                filter: [{
                    field: "name",
                    type: "equals",
                    value: "Storefront",
                }]
            });
        }).then((type) => {
            salesChannelTypeId = type.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/language?response=true', {
                filter: [{
                    field: "name",
                    type: "equals",
                    value: "Deutsch",
                }]
            });
        }).then((language) => {
            languageId = language.id;
        }).then(() => {
            return this.mergeFixtureWithData(jsonData, {
                currencyId: currencyId,
                paymentMethodId: paymentMethodId,
                shippingMethodId: shippingMethodId,
                countryId: countryId,
                languageId: languageId,
                typeId: salesChannelTypeId,
            });
        }).then((finalChannelData) => {
            return this.apiClient.post('/v1/sales-channel?_response=true', finalChannelData);
        }).catch((err) => {
            console.log('• ✖ - Error: ', err);
        }).then((salesChannel) => {
            console.log('• ✓ - Created: ', salesChannel.id);
            done();
        });
    }
}

global.SalesChannelFixtureService = new SalesChannelFixtureService();