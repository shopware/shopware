module.exports = {
    '@tags': ['product', 'manufacturer-edit', 'manufacturer', 'edit'],
    before: (browser, done) => {
        global.FixtureService.create('product-manufacturer').then(() => {
            done();
        });
    },
    'navigate to manufacturer module and look for manufacturer to be edited': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index', 'Manufacturer')
            .assert.urlContains('#/sw/manufacturer/index')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .assert.containsText('.sw-grid-row:first-child', global.FixtureService.basicFixture.name);
    },
    'open manufacturer details and change the given data': (browser) => {
        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .click('.sw-grid-row:first-child .sw-context-button__button')
            .waitForElementVisible('.sw-context-menu')
            .click('.sw-context-menu .sw-context-menu-item__text')
            .waitForElementNotPresent('.sw-loader')
            .waitForElementVisible('.smart-bar__header h2:not(.sw-manufacturer-detail__empty-title)')
            .assert.containsText('.smart-bar__header', 'MAN-U-FACTURE')
            .fillField('input[name=name]', 'Minnie\'s Haberdashery', 'input', true)
            .fillField('input[name=link]', 'https://google.com/doodles', 'input', true)
            .fillField('.ql-editor', 'A wonderfully changed description', 'editor', true)
            .click('.sw-manufacturer-detail__save-action')
            .checkNotification('Manufacturer "Minnie\'s Haberdashery" has been saved successfully.')
            .click('.sw-button__content');
    },
    after: (browser) => {
        browser.end();
    }
};
