const manufacturerPage = require('administration/page-objects/module/sw-manufacturer.page-object.js');

module.exports = {
    '@tags': ['product', 'manufacturer-edit', 'manufacturer', 'edit'],
    before: (browser, done) => {
        global.FixtureService.create('product-manufacturer').then(() => {
            done();
        });
    },
    'navigate to manufacturer module and look for manufacturer to be edited': (browser) => {
        const page = manufacturerPage(browser);

        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index', 'Manufacturer')
            .assert.urlContains('#/sw/manufacturer/index')
            .waitForElementVisible(`${page.elements.gridRow}:first-child .sw-context-button__button`)
            .assert.containsText(`${page.elements.gridRow}:first-child`, global.FixtureService.basicFixture.name);
    },
    'open manufacturer details and change the given data': (browser) => {
        const page = manufacturerPage(browser);

        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible(`${page.elements.gridRow}:first-child .sw-context-button__button`)
            .click(`${page.elements.gridRow}:first-child ${page.elements.contextMenuButton}`)
            .waitForElementVisible(page.elements.contextMenu)
            .click(`${page.elements.contextMenu} .sw-context-menu-item__text`)
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(`${page.elements.smartBarHeader} h2:not(.sw-manufacturer-detail__empty-title)`)
            .assert.containsText(page.elements.smartBarHeader, 'MAN-U-FACTURE')
            .fillField('input[name=name]', 'Minnie\'s Haberdashery', true)
            .fillField('input[name=link]', 'https://google.com/doodles', true)
            .fillField('.ql-editor', 'A wonderfully changed description', true, 'editor')
            .click(page.elements.manufacturerSave)
            .checkNotification('Manufacturer "Minnie\'s Haberdashery" has been saved successfully.')
            .click('.sw-button__content');
    },
    after: (browser) => {
        browser.end();
    }
};
