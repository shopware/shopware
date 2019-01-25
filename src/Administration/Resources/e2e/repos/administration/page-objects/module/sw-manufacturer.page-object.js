const GeneralPageObject = require('../sw-general.page-object');

class ManufacturerPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = Object.assign(this.elements,{
            manufacturerSave: '.sw-manufacturer-detail__save-action'
        });
    }

    createBasicManufacturer(manufacturerName) {
        this.browser
            .assert.containsText(this.elements.smartBarHeader, 'New manufacturer')
            .fillField('input[name=name]', manufacturerName)
            .fillField('input[name=link]', 'https://www.google.com/doodles')
            .fillField('.ql-editor', 'De-scribe THIS!', false, 'editor')
            .click(this.elements.manufacturerSave)
            .checkNotification(`Manufacturer "${manufacturerName}" has been saved successfully.`);
    }

    addManufacturerLogo(imagePath) {
        this.browser
            .click('.sw-media-upload__switch-mode')
            .waitForElementVisible('.sw-media-url-form__url-input')
            .fillField('input[name=sw-field--url]', imagePath)
            .click('.sw-media-url-form__submit-button')
            .checkNotification(``);
    }

    deleteManufacturer(manufacturerName) {
        this.browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementNotPresent('.sw-loader')
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button', '.sw-grid-row:first-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText(
                `${this.elements.modal}__body`,
                `Are you sure you want to delete the manufacturer "${manufacturerName}"?`
            )
            .click(`${this.elements.modal}__footer ${this.elements.primaryButton}`)
            .waitForElementNotPresent(this.elements.modal);
    }
}

module.exports = (browser) => {
    return new ManufacturerPageObject(browser);
};
