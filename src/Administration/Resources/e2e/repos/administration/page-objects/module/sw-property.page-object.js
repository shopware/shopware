const GeneralPageObject = require('../sw-general.page-object');

class PropertyPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                mediaForm: '.sw-product-media-form',
                propertySaveAction: '.sw-property-detail__save-action',
                productListName: `${this.elements.dataGridColumn}--name`
            }
        };
    }
}

module.exports = (browser) => {
    return new PropertyPageObject(browser);
};
