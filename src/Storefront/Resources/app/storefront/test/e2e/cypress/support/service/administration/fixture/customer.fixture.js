const AdminFixtureService = require('../fixture.service.js');

export default class CustomerFixtureService extends AdminFixtureService {
    setCustomerFixture(customerJson, customerAddressJson) {
        const addressId = this.createUuid();
        const customerId = this.createUuid();
        let countryId = '';
        let salutationId = '';
        let finalCustomerRawData = {};
        let finalAddressRawData = {};

        const findCountryId = () => this.search('country', {
            field: 'iso',
            type: 'equals',
            value: 'DE'
        });
        const findPaymentMethodId = () => this.search('payment-method', {
            type: 'equals',
            value: 'Invoice'
        });
        const findSalesChannelId = () => this.search('sales-channel', {
            type: 'equals',
            value: 'Storefront'
        });
        const findGroupId = () => this.search('customer-group', {
            type: 'equals',
            value: 'Standard customer group'
        });
        const findSalutationId = () => this.search('salutation', {
            field: 'displayName',
            type: 'equals',
            value: 'Mr.'
        });

        return Promise.all([
            findCountryId(),
            findPaymentMethodId(),
            findSalesChannelId(),
            findGroupId(),
            findSalutationId()
        ])
            .then(([country, paymentMethod, salesChannel, customerGroup, salutation]) => {
                countryId = country.id;
                salutationId = salutation.id;

                finalCustomerRawData = this.mergeFixtureWithData(customerJson, {
                    salutationId: salutation.id,
                    defaultPaymentMethodId: paymentMethod.id,
                    salesChannelId: salesChannel.id,
                    groupId: customerGroup.id,
                    defaultBillingAddressId: addressId,
                    defaultShippingAddressId: addressId
                });
            })
            .then(() => {
                finalAddressRawData = this.mergeFixtureWithData({
                    addresses: [{
                        customerId: customerId,
                        salutationId: salutationId,
                        id: addressId,
                        countryId: countryId
                    }, {
                        customerId: customerId,
                        salutationId: salutationId,
                        id: this.createUuid(),
                        countryId: countryId
                    }]
                }, customerAddressJson);
            })
            .then(() => {
                return this.mergeFixtureWithData(finalCustomerRawData, finalAddressRawData);
            })
            .then((finalCustomerData) => {
                return this.apiClient.post('/v1/customer?_response=true', finalCustomerData);
            });
    }
}

global.CustomerFixtureService = new CustomerFixtureService();
