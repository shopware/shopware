const AdminFixtureService = require('../fixture.service.js').default;

export default class ShippingFixtureService extends AdminFixtureService {
    constructor() {
        super();

        this.propertyFixture = this.loadJson('shipping-method.json');
    }

    setShippingFixture(options, userData) {
        const propertyJson = {
            ...this.propertyFixture,
            ...options
        };
        const finalData = this.mergeFixtureWithData(propertyJson, userData);
        const startTime = new Date();
        let ruleId = '';
        let deliveryTimeId = '';

        return this.search('rule', {
            value: 'Cart >= 0 (Payment)'
        }).then((data) => {
            ruleId = data.id;
        }).then(() => {
            return this.search('delivery-time', {
                value: '3-4 weeks'
            });
        }).then((data) => {
            deliveryTimeId = data.id;
        })
            .then(() => {
                return this.mergeFixtureWithData(finalData, {
                    availabilityRuleId: ruleId,
                    deliveryTimeId: deliveryTimeId
                });
            })
            .then((finalShippingData) => {
                global.logger.title('Set shipping fixtures...');

                return this.apiClient.post('/v1/shipping-method?_response=true', finalShippingData);
            })
            .then((data) => {
                const endTime = new Date() - startTime;
                global.logger.success(`${data.id} (${endTime / 1000}s)`);
                global.logger.lineBreak();
            })
            .catch((err) => {
                global.logger.error(err);
                global.logger.lineBreak();
            });
    }
}

global.ShippingFixtureService = new ShippingFixtureService();
