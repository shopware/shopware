const FixtureService = require('administration/service/fixture.service.js').default;

export default class ProductFixture extends FixtureService {
    constructor() {
        super();

        this.productFixture = this.loadJson('product.json');
    }

    setProductBaseFixture(json) {
        this.productFixture = json;
    }

    setProductFixtures(userData) {
        console.log('### Set product fixtures...');

        const productData = this.productFixture;

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
        }).then(() => {
            return Object.assign({}, {
                taxId: taxId,
                manufacturerId: manufacturerId
            }, productData);
        }).then((finalProductData) => {
            return this.apiClient.post('/v1/product?_response=true', finalProductData, userData);
        }).catch((err) => {
            console.log('• ✖ - Error: ', err);
        }).then((product) => {
            console.log('• ✓ - Created: ', product.id);
        });
    }
}

global.ProductFixtureService = new ProductFixture();