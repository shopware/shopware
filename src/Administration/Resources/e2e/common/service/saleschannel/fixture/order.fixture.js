const SalesChannelFixtureService = require('../../saleschannel/fixture.service.js').default;

export default class OrderFixtureService extends SalesChannelFixtureService {
    constructor() {
        super();

        this.customerStorefrontFixture = this.loadJson('storefront-customer.json');
    }

    search(type, filter) {
        return global.AdminFixtureService.search(type, filter).then((result) => {
            return result;
        });
    }

    createGuestOrder(productId) {
        const startTime = new Date();
        let salutationId = '';
        let customerRawData = this.customerStorefrontFixture;

        global.logger.title('Set guest order fixtures...');

        return global.AdminFixtureService.getClientId()
            .then((result) => {
                return this.apiClient.setAccessKey(result);
            }).then(() => {
                return this.search('salutation', { identifier: 'displayName', value: 'Mr.' });
            }).then((salutation) => {
                salutationId = salutation.id;
            })
            .then(() => {
                return this.search('country', { identifier: 'iso', value: 'DE' });
            })
            .then((country) => {
                customerRawData = this.mergeFixtureWithData(customerRawData, {
                    salutationId: salutationId,
                    billingAddress: {
                        salutationId: salutationId,
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
                    type: 'product'
                });
            })
            .then(() => {
                return this.apiClient.post('/v1/checkout/guest-order', customerRawData);
            })
            .then((result) => {
                const endTime = new Date() - startTime;
                global.logger.success(`${result.data.id} (${endTime / 1000}s)`);
                global.logger.lineBreak();
            })
            .catch((err) => {
                global.logger.error(err);
                global.logger.lineBreak();
            });
    }
}

global.OrderFixtureService = new OrderFixtureService();
