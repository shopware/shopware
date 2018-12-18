const FixtureService = require('administration/service/fixtures.service');

export default class ProductFixtureService extends FixtureService {
    constructor() {
        super();
    }

    setProductFixtures(productData, done) {
        console.log('### Set product fixtures...');

        let manufacturerId = '';
        let taxId = '';

        return this.apiClient.post('/v1/search/tax', {
            filter: [{
                field: "tax.name",
                type: "equals",
                value: "19%",
            }]
        }).then((data) => {
            taxId = data.id;
            return data.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/product-manufacturer', {
                filter: [{
                    field: "name",
                    type: "equals",
                    value: "shopware AG",
                }]
            });
        }).then((data) => {
            manufacturerId = data.id;
            return data.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/product-manufacturer', {
                filter: [{
                    field: "name",
                    type: "equals",
                    value: "shopware AG",
                }]
            });
        }).then((data) => {
            manufacturerId = data.id;
            return data.id;
        }).then(() => {
            return Object.assign({}, {
                taxId: taxId,
                manufacturerId: manufacturerId
            }, productData);
        }).then((finalProductData) => {
            return this.apiClient.post('/v1/product?_response=true', finalProductData);
        }).catch((err) => {
            console.log('• ✖ - Error: ', err);
        }).then((product) => {
            console.log('• ✓ - Created: ', product.id);
            done();
        });
    }
}

global.ProductFixtureService = new ProductFixtureService();