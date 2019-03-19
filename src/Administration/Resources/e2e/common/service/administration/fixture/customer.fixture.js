const AdminFixtureService = require('./../fixture.service.js').default;

export default class CustomerFixtureService extends AdminFixtureService {
    constructor() {
        super();

        this.customerFixture = this.loadJson('customer.json');
        this.customerAddressFixture = this.loadJson('customer-address.json');
    }

    setCustomerFixture(userData) {
        const startTime = new Date();
        global.logger.title('Set customer fixtures...');

        const customerJson = this.customerFixture;
        const customerAddressJson = this.customerAddressFixture;

        const addressId = this.createUuid();
        const customerId = this.createUuid();
        let countryId = '';
        let paymentMethodId = '';
        let salesChannelId = '';
        let groupId = '';
        let salutationId = '';
        let finalAddressRawData = {};

        return this.apiClient.post('/v1/search/country?response=true', {
            filter: [{
                field: 'iso',
                type: 'equals',
                value: 'DE'
            }]
        }).then((country) => {
            countryId = country.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/payment-method?response=true', {
                filter: [{
                    field: 'name',
                    type: 'equals',
                    value: 'Invoice'
                }]
            });
        }).then((paymentMethod) => {
            paymentMethodId = paymentMethod.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/sales-channel?response=true', {
                filter: [{
                    field: 'name',
                    type: 'equals',
                    value: 'Storefront API'
                }]
            });
        }).then((salesChannel) => {
            salesChannelId = salesChannel.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/customer-group?response=true', {
                filter: [{
                    field: 'name',
                    type: 'equals',
                    value: 'Standard customer group'
                }]
            });
        }).then((group) => {
            groupId = group.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/salutation?response=true', {
                filter: [{
                    field: 'name',
                    type: 'equals',
                    value: 'Mr.'
                }]
            });
        }).then((salutation) => {
            salutationId = salutation.id;
        }).then(() => {
            finalAddressRawData = this.mergeFixtureWithData({
                addresses: [{
                    customerId: customerId,
                    salutationId: salutationId,
                    id: addressId,
                    countryId: countryId
                }]
            }, customerAddressJson);
        }).then(() => {
            return this.mergeFixtureWithData(customerJson, {
                salutationId: salutationId,
                defaultPaymentMethodId: paymentMethodId,
                salesChannelId: salesChannelId,
                groupId: groupId,
                defaultBillingAddressId: addressId,
                defaultShippingAddressId: addressId
            });
        }).then((finalCustomerRawData) => {
            return this.mergeFixtureWithData(finalCustomerRawData, finalAddressRawData, userData);
        }).then((finalCustomerData) => {
            return this.apiClient.post('/v1/customer?_response=true', finalCustomerData);
        }).then((data) => {
            const endTime = new Date() - startTime;
            global.logger.success(`${data.id} (${endTime / 1000}s)`);
            global.logger.lineBreak();
        }).catch((err) => {
            global.logger.error(err);
            global.logger.lineBreak();
        });
    }
}

global.CustomerFixtureService = new CustomerFixtureService();
