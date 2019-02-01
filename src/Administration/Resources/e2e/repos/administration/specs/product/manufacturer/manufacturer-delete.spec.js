const manufacturerPage = require('administration/page-objects/module/sw-manufacturer.page-object.js');

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
        const page = manufacturerPage(browser);

        browser
            .waitForElementPresent(`${page.elements.gridRow}--0 .sw-button__content`)
            .assert.urlContains('#/sw/manufacturer/index')
            .assert.containsText(page.elements.smartBarHeader, 'Manufacturer')
            .waitForElementVisible(`${page.elements.gridRow}--0  ${page.elements.contextMenuButton}`)
            .assert.containsText(`${page.elements.gridRow}--0`, global.FixtureService.basicFixture.name);
    },
    'verify manufacturer details': (browser) => {
        const page = manufacturerPage(browser);

        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .click(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .waitForElementVisible(page.elements.contextMenu)
            .click(`${page.elements.contextMenu} .sw-context-menu-item__text`)
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(`${page.elements.smartBarHeader} h2:not(.sw-manufacturer-detail__empty-title)`)
            .assert.containsText(page.elements.smartBarHeader, global.FixtureService.basicFixture.name);
    },
    'enter manufacturer logo': (browser) => {
        const page = manufacturerPage(browser);
        page.addManufacturerLogo(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);
    },
    'delete manufacturer': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index', 'Manufacturer');

        const page = manufacturerPage(browser);
        page.deleteManufacturer(global.FixtureService.basicFixture.name);

        browser
            .waitForElementVisible('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(`${page.elements.gridRow}--0`)
            .assert.containsText(`${page.elements.gridRow}--0`, 'shopware AG')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)');
    },
    after: (browser) => {
        browser.end();
    }
};
