const FixtureService = require('administration/service/fixture.service.js').default;

export default class CustomerFixtureService extends FixtureService {
    constructor() {
        super();

        this.customerFixture = this.loadJson('customer.json');
        this.customerAddressFixture = this.loadJson('customer-address.json');
    }

    setCustomerBaseFixture(json) {
        this.customerFixture = json;
    }

    setCustomerAddressBaseFixture(json) {
        this.customerAddressFixture = json;
    }

    setCustomerFixture(userData) {
        global.logger.lineBreak();
        global.logger.title('Set customer fixtures...');

        const customerJson = this.customerFixture;
        const customerAddressJson = this.customerAddressFixture;

        const addressId = this.createUuid();
        const customerId = this.createUuid();
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
            }, customerAddressJson);
        }).then(() => {
            return this.mergeFixtureWithData(customerJson, {
                defaultPaymentMethodId: paymentMethodId,
                salesChannelId: salesChannelId,
                groupId: groupId,
                defaultBillingAddressId: addressId,
                defaultShippingAddressId: addressId,
            });
        }).then((finalCustomerRawData) => {
            return this.mergeFixtureWithData(finalCustomerRawData, finalAddressRawData, userData);
        }).then((finalCustomerData) => {
            return this.apiClient.post('/v1/customer?_response=true', finalCustomerData);
        }).catch((err) => {
            global.logger.error(err);
            global.logger.lineBreak();
        }).then((data) => {
            global.logger.success(data.id);
            global.logger.lineBreak();
        });
    }
}

global.CustomerFixtureService = new CustomerFixtureService();
