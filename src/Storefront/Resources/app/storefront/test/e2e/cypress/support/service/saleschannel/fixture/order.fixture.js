const SalesChannelFixtureService = require('../fixture.service.js');

export default class OrderFixtureService extends SalesChannelFixtureService {
    createGuestOrder(productId, json) {
        let customerRawData = json;

        const findSalutationId = () => this.search('salutation', {
            field: 'displayName',
            type: 'equals',
            value: 'Mr.'
        });
        const findCountryId = () => this.search('country', {
            field: 'iso',
            type: 'equals',
            value: 'DE'
        });

        return this.getClientId()
            .then((result) => {
                this.apiClient.setAccessKey(result);
            })
            .then(() => {
                return Promise.all([
                    findSalutationId(),
                    findCountryId()
                ]);
            })
            .then(([salutation, country]) => {
                customerRawData = this.mergeFixtureWithData(customerRawData, {
                    salutationId: salutation.id,
                    billingAddress: {
                        salutationId: salutation.id,
                        countryId: country.id
                    }
                });
            })
            .then(() => {
                return this.apiClient.setContextToken(this.createUuid().replace(/-/g, ''));
            })
            .then(() => {
                return this.apiClient.post('/v1/checkout/cart');
            })
            .then(() => {
                return this.apiClient.post(`/v1/checkout/cart/line-item/${productId}`, {
                    type: 'product',
                    referencedId: productId,
                    stackable: true
                });
            })
            .then(() => {
                return this.apiClient.post('/v1/checkout/guest-order', customerRawData);
            })
            .catch((err) => {
                console.log('err :', err);
            });
    }
}

global.OrderFixtureService = new OrderFixtureService();
