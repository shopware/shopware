class ManufacturerPageObject {
    constructor(browser) {
        this.browser = browser;
    }

    createBasicManufacturer(manufacturerName) {
        this.browser
            .assert.containsText('.smart-bar__header', 'New manufacturer')
            .fillField('input[name=name]', manufacturerName)
            .fillField('input[name=link]', 'https://www.google.com/doodles')
            .fillField('.ql-editor', 'De-scribe THIS!', false, 'editor')
            .click('.sw-button--primary')
            .checkNotification(`Manufacturer "${manufacturerName}" has been saved successfully.`);
    }

    addManufacturerLogo(imagePath) {
        this.browser
            .click('.sw-media-upload__switch-mode')
            .waitForElementVisible('.sw-media-url-form__url-input')
            .fillField('input[name=sw-field--url]', imagePath)
            .click('.sw-media-url-form__submit-button')
            .waitForElementVisible('.sw-alert--success')
            .click('.sw-alert .sw-alert__close')
            .waitForElementNotPresent('.sw-alert--success')
            .click('.sw-button--primary')
            .waitForElementVisible('.sw-alert')
            .waitForElementPresent('.sw-button__content')
            .click('.sw-alert .sw-alert__close');
    }

    deleteManufacturer(manufacturerName) {
        this.browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementNotPresent('.sw-loader')
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button', '.sw-grid-row:first-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText(
                '.sw-modal__body',
                `Are you sure you want to delete the manufacturer "${manufacturerName}"?`
            )
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent('.sw-modal');
    }
}

module.exports = (browser) => {
    return new ManufacturerPageObject(browser);
};
