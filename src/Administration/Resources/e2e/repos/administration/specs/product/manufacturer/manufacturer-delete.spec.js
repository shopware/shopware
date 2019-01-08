const manufacturerPage = require('administration/page-objects/sw-manufacturer.page-object.js');
const mediaPageObject = require('administration/page-objects/sw-media.page-object.js');

module.exports = {
    '@tags': ['product', 'manufacturer-delete', 'manufacturer', 'delete'],
    before: (browser, done) => {
        global.FixtureService.create('product-manufacturer').then(() => {
            done();
        });
    },
    'navigate to manufacturer module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index', 'Manufacturer')
            .assert.urlContains('#/sw/manufacturer/index');
    },
    'check if new manufacturer exists in overview': (browser) => {
        browser
            .waitForElementPresent('.sw-button__content')
            .assert.urlContains('#/sw/manufacturer/index')
            .assert.containsText('.smart-bar__header', 'Manufacturer')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .assert.containsText('.sw-grid-row:first-child', global.FixtureService.basicFixture.name);
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
            .assert.containsText('.smart-bar__header', global.FixtureService.basicFixture.name);
    },
    'enter manufacturer logo': (browser) => {
        const page = manufacturerPage(browser);
        page.addManufacturerLogo(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);
    },
    'delete manufacturer, including its logo': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index', 'Manufacturer');

        const page = manufacturerPage(browser);
        const mediaPage = mediaPageObject(browser);
        page.deleteManufacturer(global.FixtureService.basicFixture.name);

        browser
            .waitForElementVisible('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible('.sw-grid-row:first-child')
            .assert.containsText('.sw-grid-row:first-child', 'shopware AG')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)');

        mediaPage.openMediaFolder();

        if (flags.isActive('next1207')) {
            // move into manufacturer media folder
            browser.clickContextMenuItem('.sw-context-menu-item','.sw-context-button__button');
        }

        mediaPage.deleteImage();
        browser.waitForElementNotPresent('.sw-media-index__catalog-grid .sw-media-grid__content-cell');
    },
    after: (browser) => {
        browser.end();
    }
};
