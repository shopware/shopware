const mediaPage = require('administration/page-objects/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'folder', 'folder-set-default', 'set-default'],
    '@disabled': !flags.isActive('next1207'),
    before: (browser, done) => {
        global.MediaFixtureService.setFolderFixture({
            name: 'Products for the good'
        }).then(() => {
            done();
        });
    },
    'open media listing': (browser) => {
        browser
            .openMainMenuEntry('#/sw/media/index', 'Media')
            .assert.urlContains('#/sw/media/index');
    },
    'open settings and set as default for products': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-media-base-item__preview-container')
            .click('.sw-media-base-item__preview-container')
            .waitForElementVisible('.quickaction--settings')
            .click('.quickaction--settings')
            .waitForElementVisible('.sw-modal__title')
            .assert.containsText('.sw-modal__title', 'Products for the good')
            .waitForElementVisible('.sw-select')
            .assert.containsText('.sw-select label', 'Default location for:')
            .fillSwSelectComponent(
                '.sw-select__inner',
                {
                    value: 'Product Media',
                    isMulti: false,
                    searchTerm: 'Product Media'
                }
            )
            .waitForElementVisible('.sw-select__single-selection')
            .assert.containsText('.sw-select__single-selection', 'Product Media')
            .waitForElementVisible(page.elements.saveSettingsAction)
            .click(page.elements.saveSettingsAction)
            .checkNotification('Settings have been saved successfully')
            .waitForElementVisible('.icon--default-symbol-products');


    },
    'check if the folder is used as default location when uploading in products': (browser) => {
        const page = mediaPage(browser);

        browser
            .openMainMenuEntry('#/sw/product/index', 'Products')
            .waitForElementPresent('.smart-bar__actions a[href="#/sw/product/create"]')
            .click('.smart-bar__actions a[href="#/sw/product/create"]')
            .waitForElementVisible('.sw-sidebar-navigation-item')
            .click('.sw-sidebar-navigation-item')
            .waitForElementVisible(page.elements.folderNameLabel)
            .expect.element('.sw-media-base-item__name').to.have.text.that.equals('Products for the good');
    },
    after: (browser) => {
        browser.end();
    }
};
