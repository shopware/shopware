const GeneralPageObject = require('../sw-general.page-object');

class ManufacturerPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements, ...{
                manufacturerSave: '.sw-manufacturer-detail__save-action'
            }
        };
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
            .checkNotification('File has been saved successfully.');
    }

    deleteManufacturer(manufacturerName) {
        this.browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementNotPresent(this.elements.loader)
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementNotPresent(this.elements.loader)
            .clickContextMenuItem('.sw-context-menu-item--danger', this.elements.contextMenuButton, `${this.elements.gridRow}--0`)
            .expect.element(`${this.elements.modal}__body`).text.that.equals(`Are you sure you want to delete the manufacturer "${manufacturerName}"?`);

        this.browser
            .click(`${this.elements.modal}__footer ${this.elements.primaryButton}`)
            .waitForElementNotPresent(this.elements.modal);
    }
}

module.exports = (browser) => {
    return new ManufacturerPageObject(browser);
};
