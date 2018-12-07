class ProductPageObject {
    constructor(browser) {
        this.browser = browser;

        this.elements = {};
        this.elements.columnProductName = 'sw-product-list__column-product-name > a';
    }

    createBasicProduct(productName) {
        this.browser
            .fillField('input[name=sw-field--product-name]', productName)
            .fillField('.ql-editor', 'My very first description', 'editor')
            .fillSelectField('select[name=sw-field--product-manufacturerId]', 'shopware AG')
            .fillSelectField('select[name=sw-field--product-catalogId]', 'Default catalogue')
            .fillSelectField('select[name=sw-field--product-taxId]', '19%')
            .fillField('input[name=sw-field--price-gross]', '99')
            .click('.sw-product-detail__save-action')
            .checkNotification(`Product "${productName}" has been saved successfully`);
    }

    addProductImageViaUrl(imagePath, productName) {
        this.browser
            .waitForElementPresent('.sw-product-media-form')
            .getLocationInView('.sw-product-media-form')
            .waitForElementVisible('.sw-product-media-form')
            .waitForElementVisible('.sw-media-upload__switch-mode')
            .click('.sw-media-upload__switch-mode')
            .waitForElementVisible('.sw-media-url-form__url-input')
            .fillField('input[name=sw-field--url]', imagePath)
            .waitForElementNotPresent('input[name=sw-field--extensionFromInput]')
            .waitForElementNotPresent('.sw-alert--info')
            .click('.sw-media-url-form__submit-button')
            .getAttribute('.sw-media-preview > img', 'src', function (result) {
                this.assert.ok(result.value);
                this.assert.equal(result.value, imagePath);
            })
            .click('.sw-product-detail__save-action')
            .checkNotification('1 of 1 files saved', false)
            .click('.sw-alert__close')
            .useXpath()
            .waitForElementNotPresent(`//*[contains(text(), '1 of 1 files saved')]`)
            .useCss()
            .checkNotification(`Product "${productName}" has been saved successfully`, false)
            .click('.sw-alert__close')
            .useXpath()
            .waitForElementNotPresent(`//*[contains(text(), 'Product "${productName}" has been saved successfully')]`)
            .useCss()
            .checkNotification('File has been saved successfully');
    }

    deleteProduct(productName) {
        this.browser
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button', '.sw-grid-row:first-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-product-list__confirm-delete-text', `Are you sure you really want to delete the product "${productName}"?`)
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent('.sw-modal')
            .waitForElementNotPresent(this.elements.columnProductName);
    }
}

module.exports = (browser) => {
    return new ProductPageObject(browser);
};
