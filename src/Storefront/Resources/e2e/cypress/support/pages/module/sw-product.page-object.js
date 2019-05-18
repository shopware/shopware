const GeneralPageObject = require('../sw-general.page-object');

export default class ProductPageObject extends GeneralPageObject {
    constructor() {
        super();

        this.elements = {
            ...this.elements,
            ...{
                productSaveAction: '.sw-product-detail__save-action',
                productListName: `${this.elements.dataGridColumn}--name`
            }
        };
    }
}
