const StorefrontFixtureService = require('../../storefront/fixture.service.js').default;

export default class OrderFixtureService extends StorefrontFixtureService {
    constructor() {
        super();

        this.customerStorefrontFixture = this.loadJson('storefront-customer.json');
    }

    createGuestOrder(productId) {
        const startTime = new Date();
        let customerRawData = this.customerStorefrontFixture;

        global.logger.title('Set guest order fixtures...');

        return global.AdminFixtureService.getClientId().then((result) => {
            return this.apiClient.setAccessKey(result);
        }).then(() => {
            return this.adminApiClient.post('/v1/search/country?response=true', {
                filter: [{
                    field: "iso",
                    type: "equals",
                    value: "DE",
                }]
            });
        }).then((country) => {
            customerRawData = this.mergeFixtureWithData(customerRawData, {
                "billingAddress.country": country.id,
            });
        }).then(() => {
            return this.apiClient.setContextToken(this.createUuid().replace(/-/g,''));
        }).then(() => {
            return this.apiClient.post('/v1/checkout/cart');
        }).then(() => {
            return this.apiClient.post(`/v1/checkout/cart/line-item/${productId}`, {
                type: 'product'
            })
        }).then(() => {
            return this.apiClient.post(`/v1/checkout/guest-order`, customerRawData)
        }).then((result) => {
            const endTime = new Date() - startTime;
            global.logger.success(`${result['data'].id} (${endTime / 1000}s)`);
            global.logger.lineBreak();
        }).catch((err) => {
            global.logger.error(err);
            global.logger.lineBreak();
        });
    }
}

global.OrderFixtureService = new OrderFixtureService();