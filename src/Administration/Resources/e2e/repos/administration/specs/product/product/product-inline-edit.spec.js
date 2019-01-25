const productPage = require('administration/page-objects/module/sw-product.page-object.js');

const fixture = {
    name: 'Beautiful Product'
};

module.exports = {
    '@tags': ['product', 'product-inline-edit', 'inline-edit'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixtures(fixture).then(() => {
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
            .waitForElementVisible(`${page.elements.gridRow}:first-child ${page.elements.contextMenuButton}`)
            .assert.containsText(page.elements.productListName, fixture.name);
    },
    'edit product name via inline editing and verify edit': (browser) => {
        const page = productPage(browser);

        browser
            .waitForElementVisible(`.sw-grid-row:first-child .sw-context-button__button`)
            .moveToElement(`.sw-grid-row:first-child`, 5, 5).doubleClick()
            .fillField('input[name=sw-field--item-name]', 'Cyberdyne Systems T800', true)
            .waitForElementVisible(`${page.elements.gridRow}__inline-edit-action`)
            .click(`${page.elements.gridRow}__inline-edit-action`)
            .waitForElementNotPresent('.is--inline-editing')
            .refresh()
            .waitForElementVisible(page.elements.productListName)
            .assert.containsText(page.elements.productListName, 'Cyberdyne Systems T800')
            .moveToElement(`${page.elements.gridRow}:last-child`, 5, 5).doubleClick()
            .fillField('.is--inline-editing .sw-field__input input', 'Skynet Robotics T1000', true)
            .waitForElementVisible(`${page.elements.gridRow}__inline-edit-action`)
            .click(`${page.elements.gridRow}__inline-edit-action`)
            .waitForElementNotPresent('.is--inline-editing ')
            .assert.containsText(`${page.elements.gridRow}:last-child ${page.elements.productListName}`, 'Skynet Robotics T1000');
    },
    after: (browser) => {
        browser.end();
    }
};
