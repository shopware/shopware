const manufacturerPage = require('administration/page-objects/module/sw-manufacturer.page-object.js');

module.exports = {
    '@tags': ['product', 'manufacturer-create', 'manufacturer', 'create', 'upload'],
    'navigate to manufacturer module and click on add manufacturer': (browser) => {
        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/product/index',
                menuTitle: 'Product',
                index: 1,
                subMenuItemPath: '#/sw/manufacturer/index',
                subMenuTitle: 'Manufacturer'
            })
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
            .openMainMenuEntry({
                mainMenuPath: '#/sw/product/index',
                menuTitle: 'Product',
                index: 1,
                subMenuItemPath: '#/sw/manufacturer/index',
                subMenuTitle: 'Manufacturer'
            })
            .refresh()
            .waitForElementPresent(`${page.elements.gridRow}--0 .sw-manufacturer-list_column-manufacturer-name`)
            .assert.urlContains('#/sw/manufacturer/index')
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains('MAN-U-FACTURE');
    },
    'verify manufacturer details': (browser) => {
        const page = manufacturerPage(browser);

        browser
            .refresh()
            .clickContextMenuItem('.sw-manufacturer-list__edit-action', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('MAN-U-FACTURE');
    },
    'check if the manufacturer can be used in product': (browser) => {
        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/product/index',
                menuTitle: 'Products',
                index: 1
            })
            .waitForElementPresent('.smart-bar__actions a[href="#/sw/product/create"]')
            .click('.smart-bar__actions a[href="#/sw/product/create"]')
            .waitForElementVisible('.sw-product-detail-base')
            .fillSelectField('select[name=sw-field--product-manufacturerId]', 'MAN-U-FACTURE');
    },
    after: (browser) => {
        browser.end();
    }
};
