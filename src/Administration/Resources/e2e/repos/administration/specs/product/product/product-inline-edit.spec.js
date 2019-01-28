const productPage = require('administration/page-objects/module/sw-product.page-object.js');

const fixture = {
    name: 'Beautiful Product'
};

module.exports = {
    '@tags': ['product', 'product-inline-edit', 'inline-edit'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixture(fixture).then(() => {
            done();
        });
    },
    'open product listing and look for the product to be edited': (browser) => {
        const page = productPage(browser);

        browser
            .openMainMenuEntry('#/sw/product/index', 'Products')
            .waitForElementVisible('.smart-bar__actions a')
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(1)')
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .assert.containsText(page.elements.productListName, fixture.name);
    },
    'edit product name via inline editing and verify edit': (browser) => {
        const page = productPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .moveToElement(`${page.elements.gridRow}--0`, 5, 5).doubleClick()
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`, 'Cyberdyne Systems T800', true)
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .click(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .waitForElementNotPresent('.is--inline-editing')
            .refresh()
            .waitForElementVisible(page.elements.productListName)
            .assert.containsText(page.elements.productListName, 'Cyberdyne Systems T800');
    },
    after: (browser) => {
        browser.end();
    }
};
