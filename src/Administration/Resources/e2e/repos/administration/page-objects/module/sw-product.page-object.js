const GeneralPageObject = require('../sw-general.page-object');

class ProductPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements, ...{
                columnProductName: 'sw-product-list__column-product-name > a',
                mediaForm: '.sw-product-media-form',
                productSaveAction: '.sw-product-detail__save-action',
                productListName: '.sw-product-list__column-product-name'
            }
        };
    }

    createBasicProduct(productName) {
        this.browser
            .fillField('input[name=sw-field--product-name]', productName)
            .fillField('.ql-editor', 'My very first description', false, 'editor')
            .fillSelectField('select[name=sw-field--product-manufacturerId]', 'shopware AG')
            .fillSelectField('select[name=sw-field--product-taxId]', '19%')
            .fillField('input[name=sw-field--price-gross]', '99')
            .click(this.elements.productSaveAction)
            .checkNotification(`Product "${productName}" has been saved successfully.`);
    }

    addProductImageViaUrl(imagePath, productName) {
        this.browser
            .waitForElementPresent(this.elements.mediaForm)
            .getLocationInView(this.elements.mediaForm)
            .waitForElementVisible(this.elements.mediaForm)
            .click('.sw-media-upload__switch-mode')
            .fillField('input[name=sw-field--url]', imagePath)
            .click('.sw-media-url-form__submit-button')
            .waitForElementNotPresent('input[name=sw-field--url]')
            .waitForElementVisible('.sw-media-preview__item')
            .checkNotification('File has been saved successfully.')
            .click(this.elements.productSaveAction)
            .checkNotification(`Product "${productName}" has been saved successfully.`);
    }

    deleteProduct(productName) {
        this.browser
            .clickContextMenuItem(`${this.elements.contextMenu}-item--danger`, this.elements.contextMenuButton, `${this.elements.gridRow}--0`)
            .expect.element(`${this.elements.modal} .sw-product-list__confirm-delete-text`).text.that.equals(`Are you sure you really want to delete the product "${productName}"?`);

        this.browser
            .click(`${this.elements.modal}__footer button${this.elements.primaryButton}`)
            .waitForElementNotPresent(this.elements.modal)
            .waitForElementNotPresent(this.elements.columnProductName);
    }
}

module.exports = (browser) => {
    return new ProductPageObject(browser);
};
