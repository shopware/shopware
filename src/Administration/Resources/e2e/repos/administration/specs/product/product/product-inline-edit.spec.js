const productPage = require('administration/page-objects/sw-product.page-object.js');

module.exports = {
    '@tags': ['product', 'product-inline-edit', 'inline-edit'],
    'open product listing': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Products')
            .waitForElementVisible('.smart-bar__actions a')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(0)');
    },
    'create the product': (browser) => {
        const page = productPage(browser);

        browser
            .click('a[href="#/sw/product/create"]')
            .waitForElementVisible('.sw-product-detail-base')
            .assert.urlContains('#/sw/product/create')
            .assert.containsText('.sw-card__title', 'Information');

        page.createBasicProduct('First one');
        browser
            .assert.urlContains('#/sw/product/detail');
    },
    'go back to listing': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementNotPresent('.sw-alert__message')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .assert.containsText('.sw-product-list__column-product-name', 'First one');
    },
    'edit product name via inline editing and verify change': (browser) => {
        browser
            .moveToElement('.sw-grid-row:first-child', 0, 0).doubleClick()
            .fillField('input[name=sw-field--item-name]', 'Second one')
            .waitForElementVisible('.is--inline-editing .sw-button--primary')
            .click('.is--inline-editing .sw-button--primary')
            .waitForElementNotPresent('.is--inline-editing ')
            .refresh()
            .waitForElementVisible('.sw-product-list__column-product-name')
            .assert.containsText('.sw-product-list__column-product-name', 'Second one');
    },
    after: (browser) => {
        browser.end();
    }

};
