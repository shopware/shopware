const FixtureService = require('./../fixture.service.js').default;

export default class OrderFixtureService extends FixtureService {
    constructor() {
        super();

        this.customerStorefrontFixture = this.loadJson('storefront-customer.json');
    }

    createGuestOrder(productId) {
        const startTime = new Date();
        let customerRawData = this.customerStorefrontFixture;

        global.logger.title('Set guest order fixtures...');

        return global.FixtureService.getClientId().then((result) => {
            return this.storefrontApiClient.setAccessKey(result);
        }).then(() => {
            return this.apiClient.post('/v1/search/country?response=true', {
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
            return this.storefrontApiClient.setContextToken(this.createUuid().replace(/-/g,''));
        }).then(() => {
            return this.storefrontApiClient.post('/v1/checkout/cart');
        }).then(() => {
            return this.storefrontApiClient.post(`/v1/checkout/cart/line-item/${productId}`, {
                type: 'product'
            })
        }).then(() => {
            return this.storefrontApiClient.post(`/v1/checkout/guest-order`, customerRawData)
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