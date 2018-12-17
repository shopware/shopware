const manufacturerPage = require('administration/page-objects/sw-manufacturer.page-object.js');
const mediaPageObject = require('administration/page-objects/sw-media.page-object.js');

module.exports = {
    '@tags': ['product', 'manufacturer-delete', 'manufacturer', 'delete'],
    'navigate to manufacturer module and click on add manufacturer': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index', 'Manufacturer')
            .assert.urlContains('#/sw/manufacturer/index')
            .waitForElementPresent('.sw-button__content')
            .click('.sw-button__content');
    },
    'enter manufacturer information and save': (browser) => {
        const page = manufacturerPage(browser);
        page.createBasicManufacturer('MAN-U-FACTURE');
        page.addManufacturerLogo(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);
    },
    'check if new manufacturer exists in overview': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index', 'Manufacturer')
            .refresh()
            .waitForElementPresent('.sw-button__content')
            .assert.urlContains('#/sw/manufacturer/index')
            .assert.containsText('.smart-bar__header', 'Manufacturer')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .assert.containsText('.sw-grid-row:first-child', 'MAN-U-FACTURE');
    },
    'verify manufacturer details': (browser) => {
        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .click('.sw-grid-row:first-child .sw-context-button__button')
            .waitForElementVisible('.sw-context-menu')
            .click('.sw-context-menu .sw-context-menu-item__text')
            .waitForElementNotPresent('.sw-loader')
            .waitForElementVisible('.smart-bar__header h2:not(.sw-manufacturer-detail__empty-title)')
            .assert.containsText('.smart-bar__header', 'MAN-U-FACTURE');
    },
    'check if the manufacturer can be used in product': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Products')
            .waitForElementPresent('.smart-bar__actions a[href="#/sw/product/create"]')
            .click('.smart-bar__actions a[href="#/sw/product/create"]')
            .waitForElementVisible('.sw-product-detail-base')
            .fillSelectField('select[name=sw-field--product-manufacturerId]', 'MAN-U-FACTURE');
    },
    'delete manufacturer, including its logo': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index', 'Manufacturer');

        const page = manufacturerPage(browser);
        const mediaPage = mediaPageObject(browser);
        page.deleteManufacturer('MAN-U-FACTURE');

        browser
            .waitForElementVisible('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible('.sw-grid-row:first-child')
            .assert.containsText('.sw-grid-row:first-child', 'shopware AG')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)');

        mediaPage.openMediaFolder();
        mediaPage.deleteImage();
        browser.waitForElementNotPresent('.sw-media-index__catalog-grid .sw-media-grid__content-cell');
    },
    after: (browser) => {
        browser.end();
    }
};
