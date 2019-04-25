const AdminFixtureService = require('./../fixture.service.js').default;

export default class ProductFixture extends AdminFixtureService {
    constructor() {
        super();

        this.productFixture = this.loadJson('product.json');
    }

    setProductFixture(userData) {
        const startTime = new Date();
        global.logger.title('Set product fixtures...');

        const productData = this.productFixture;

        let manufacturerId = '';
        let taxId = '';

        return this.apiClient.post('/v1/search/tax', {
            filter: [{
                field: 'tax.name',
                type: 'equals',
                value: '19%'
            }]
        }).then((data) => {
            taxId = data.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/product-manufacturer', {
                filter: [{
                    field: 'name',
                    type: 'equals',
                    value: 'shopware AG'
                }]
            });
        }).then((data) => {
            manufacturerId = data.id;
        })
            .then(() => {
                return Object.assign({}, {
                    taxId: taxId,
                    manufacturerId: manufacturerId
                }, productData, userData);
            })
            .then((finalProductData) => {
                return this.apiClient.post('/v1/product?_response=true', finalProductData);
            })
            .then((data) => {
                const endTime = new Date() - startTime;
                global.logger.success(`${data.id} (${endTime / 1000}s)`);
                global.logger.lineBreak();

                return data.id;
            })
            .catch((err) => {
                global.logger.error(err);
                global.logger.lineBreak();
            });
    }

    setProductVisible(productId) {
        let salesChannelId = '';
        let categoryId = '';
        const startTime = new Date();

        return this.apiClient.post('/v1/search/sales-channel?response=true', {
            filter: [{
                field: 'name',
                type: 'equals',
                value: 'SalesChannel API endpoint'
            }]
        }).then((data) => {
            salesChannelId = data.id;
        }).then(() => {
            return this.create('category');
        }).then((data) => {
            categoryId = data.id;
        })
            .then(() => {
                global.logger.title('Set product visibility...');

                return this.update({
                    id: productId,
                    type: 'product',
                    data: {
                        visibilities: [{
                            visibility: 30,
                            salesChannelId: salesChannelId
                        }],
                        categories: [{
                            id: categoryId
                        }]
                    }
                });
            })
            .then((data) => {
                const endTime = new Date() - startTime;
                global.logger.success(`Updated product: ${data.id} (${endTime / 1000}s)`);
                global.logger.lineBreak();

                return data.id;
            })
            .catch((err) => {
                global.logger.error(err);
                global.logger.lineBreak();
            });
    }
}

global.ProductFixtureService = new ProductFixture();
