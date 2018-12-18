const FixtureService = require('administration/service/fixtures.service');
const uuid = require('uuid/v4');

export default class CustomerFixtureService extends FixtureService {
    constructor() {
        super();
    }

    setCustomerFixture(customerJson, customerAdressJson, done) {
        console.log('### Set customer fixtures...');

        const addressId = uuid();
        const customerId = uuid();
        let countryId = '';
        let paymentMethodId = '';
        let salesChannelId = '';
        let groupId = '';
        let finalAddressRawData = {};

        return this.apiClient.post('/v1/search/country?response=true', {
            filter: [{
                field: "iso",
                type: "equals",
                value: "DE",
            }]
        }).then((country) => {
            countryId = country.id;
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
            return this.apiClient.post('/v1/search/sales-channel?response=true', {
                filter: [{
                    field: "name",
                    type: "equals",
                    value: "Storefront API",
                }]
            })
        }).then((salesChannel) => {
            salesChannelId = salesChannel.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/customer-group?response=true', {
                filter: [{
                    field: "name",
                    type: "equals",
                    value: "Standard customer group",
                }]
            })
        }).then((group) => {
            groupId = group.id;
        }).then(() => {
            finalAddressRawData = this.mergeFixtureWithData({
                addresses: [{
                    customerId: customerId,
                    id: addressId,
                    countryId: countryId,
                }]
            }, customerAdressJson);
        }).then(() => {
            return this.mergeFixtureWithData(customerJson, {
                defaultPaymentMethodId: paymentMethodId,
                salesChannelId: salesChannelId,
                groupId: groupId,
                defaultBillingAddressId: addressId,
                defaultShippingAddressId: addressId,
            });
        }).then((finalCustomerRawData) => {
            return this.mergeFixtureWithData(finalCustomerRawData, finalAddressRawData);
        }).then((finalCustomerData) => {
            return this.apiClient.post('/v1/customer?_response=true', finalCustomerData);
        }).catch((err) => {
            console.log('• ✖ - Error: ', err);
        }).then((customer) => {
            console.log(`• ✓ - Created: ${customer.id}`);
            done();
        })
    }
}

global.CustomerFixtureService = new CustomerFixtureService();