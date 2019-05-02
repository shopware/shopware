const GeneralPageObject = require('../sw-general.page-object');

class ProductPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                mediaForm: '.sw-product-media-form',
                productSaveAction: '.sw-product-detail__save-action',
                productListName: `${this.elements.dataGridColumn}--name`
            }
        };
    }

    createBasicProduct(productName) {
        this.browser
            .fillField('input[name=sw-field--product-name]', productName)
            .fillField('.sw-text-editor__content-editor', 'My very first description', false, 'editor')
            .fillSwSelectComponent(
                '.sw-select-product__select_manufacturer',
                {
                    value: 'shopware AG',
                    searchTerm: 'shopware AG',
                    isMulti: false
                }
            )
            .fillField('input[name=sw-field--price-gross]', '99')
            .fillSelectField('select[name=sw-field--product-taxId]', '19%')
            .fillField('input[name=sw-field--product-stock]', '1')
            .expect.element(this.elements.productSaveAction).to.not.have.attribute('disabled');

        this.browser
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
            .click(this.elements.productSaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium');
    }

    addProductImageViaUrl(imagePath) {
        this.browser
            .waitForElementPresent(this.elements.mediaForm)
            .getLocationInView(this.elements.mediaForm)
            .waitForElementVisible(this.elements.mediaForm)
            .click('.sw-media-upload__switch-mode')
            .fillField('input[name=sw-field--url]', imagePath)
            .click('.sw-media-url-form__submit-button')
            .waitForElementNotPresent('input[name=sw-field--url]')
            .waitForElementVisible('.sw-media-preview__item')
            .checkNotification('A file has been saved successfully.')
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
            .expect.element(this.elements.productSaveAction).to.not.have.attribute('disabled');

        this.browser
            .click(this.elements.productSaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium');
    }

    deleteProduct(productName) {
        this.browser
            .clickContextMenuItem(this.elements.contextMenuButton, {
                menuActionSelector: `${this.elements.contextMenu}-item--danger`,
                scope: `${this.elements.gridRow}--0`
            })
            .expect.element(`${this.elements.modal} .sw-product-list__confirm-delete-text`).text.that.equals(`Are you sure you really want to delete the product "${productName}"?`);

        this.browser
            .click(`${this.elements.modal}__footer button${this.elements.primaryButton}`)
            .waitForElementNotPresent(this.elements.modal)
            .waitForElementNotPresent(`${this.elements.productListName} > a`);
    }

    generateVariants(propertyName, optionPosition) {
        const optionsIndicator = '.sw-property-search__tree-selection__column-items-selected.sw-grid-column--right span';
        const optionString = optionPosition.length < 1 ? 'option' : 'options';

        this.browser
            .click('.group_grid__column-name');

        for (const entry in Object.values(optionPosition)) { // eslint-disable-line
            if (optionPosition.hasOwnProperty(entry)) {
                this.browser
                    .tickCheckbox(
                        `.sw-property-search__tree-selection__option_grid .sw-grid__row--${entry} .sw-field__checkbox input`,
                        true
                    );
            }
        }

        this.browser.expect.element(`.sw-grid__row--0 ${optionsIndicator}`).to.have.text.that.contains(`${optionPosition.length} ${optionString} selected`);
        this.browser.expect.element(`.sw-modal__footer ${this.elements.primaryButton}`).to.have.text.that.contains(`${optionPosition.length} Generate variants`);
        this.browser.click(`.sw-modal__footer ${this.elements.primaryButton}`)
            .waitForElementNotPresent('.sw-product-modal-variant-generation');
    }

    findInStorefront(name) {
        this.browser
            .url(process.env.APP_URL)
            .waitForElementVisible('input[name=search]')
            .setValue('input[name=search]', name)
            .expect.element('.result-product .result-link').to.have.text.that.contains(name);

        this.browser
            .click('.result-product .result-link')
            .waitForElementVisible('.product-detail-content')
            .expect.element('.product-detail-name').to.have.text.that.contains(name);
    }

    changeTranslation(productName, language, position) {
        this.browser
            .waitForElementVisible('.sw-language-switch')
            .click('.sw-language-switch')
            .waitForElementNotPresent('.sw-field__select-load-placeholder');

        this.browser.expect.element(`.sw-select-option:nth-of-type(${position})`).to.have.text.that.equals(language);
        this.browser
            .click(`.sw-select-option:nth-of-type(${position})`)
            .waitForElementNotPresent('.sw-field__select-load-placeholder');
    }

    createProductTag(value, position = 0) {
        this.browser
            .fillField(`.sw-tag-field ${this.elements.selectInput}`, value)
            .expect.element('.sw-select__results-empty-message').to.have.text.that.equals(`No results found for "${value}".`);

        this.browser
            .setValue(`.sw-tag-field ${this.elements.selectInput}`, this.browser.Keys.ENTER)
            .expect.element(`${this.elements.selectSelectedItem}--${position}`).to.have.text.that.equals(value);

        this.browser.setValue(`.sw-tag-field ${this.elements.selectInput}`, this.browser.Keys.ESCAPE);
    }
}

module.exports = (browser) => {
    return new ProductPageObject(browser);
};
