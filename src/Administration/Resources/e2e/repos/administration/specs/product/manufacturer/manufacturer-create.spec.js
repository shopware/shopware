const manufacturerPage = require('administration/page-objects/module/sw-manufacturer.page-object.js');

module.exports = {
    '@tags': ['product', 'manufacturer-create', 'manufacturer', 'create', 'upload'],
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
        const page = manufacturerPage(browser);

        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index', 'Manufacturer')
            .refresh()
            .waitForElementPresent('.sw-button__content')
            .assert.urlContains('#/sw/manufacturer/index')
            .assert.containsText(page.elements.smartBarHeader, 'Manufacturer')
            .waitForElementVisible(`${page.elements.gridRow}:first-child ${page.elements.contextMenuButton}`)
            .assert.containsText(`${page.elements.gridRow}:first-child`, 'MAN-U-FACTURE');
    },
    'verify manufacturer details': (browser) => {
        const page = manufacturerPage(browser);

        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible(`${page.elements.gridRow}:first-child ${page.elements.contextMenuButton}`)
            .click(`${page.elements.gridRow}:first-child ${page.elements.contextMenuButton}`)
            .waitForElementVisible(page.elements.contextMenu)
            .click(`${page.elements.contextMenu} .sw-context-menu-item__text`)
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(`${page.elements.smartBarHeader} h2:not(.sw-manufacturer-detail__empty-title)`)
            .assert.containsText(page.elements.smartBarHeader, 'MAN-U-FACTURE');
    },
    'check if the manufacturer can be used in product': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Products')
            .waitForElementPresent('.smart-bar__actions a[href="#/sw/product/create"]')
            .click('.smart-bar__actions a[href="#/sw/product/create"]')
            .waitForElementVisible('.sw-product-detail-base')
            .fillSelectField('select[name=sw-field--product-manufacturerId]', 'MAN-U-FACTURE');
    },
    after: (browser) => {
        browser.end();
    }
};
