const FixtureService = require('administration/service/fixture.service').default;

export default class SalesChannelFixtureService extends FixtureService {
    constructor() {
        super();

        this.salesChannelFixture = this.loadJson('sales-channel.json');
    }

    setSalesChannelBasicFixture(json) {
        this.salesChannelFixture = json;
    }

    setSalesChannelFixture(userData) {
        this.loggingHelper.createCliEntry('Set sales channel fixtures...', 'title');

        const jsonData = this.salesChannelFixture;

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
            }, userData);
        }).then((finalChannelData) => {
            return this.apiClient.post('/v1/sales-channel?_response=true', finalChannelData);
        }).catch((err) => {
            this.loggingHelper.createCliEntry(err, 'error');
        }).then((data) => {
            this.loggingHelper.createCliEntry(data.id, 'success');
        });
    }
}

global.SalesChannelFixtureService = new SalesChannelFixtureService();