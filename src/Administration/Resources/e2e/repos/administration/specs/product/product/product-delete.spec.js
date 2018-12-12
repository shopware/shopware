const productPage = require('administration/page-objects/sw-product.page-object.js');


module.exports = {
    '@tags': ['product-delete', 'product', 'delete'],
    'create simple category to assign the product to it later ': (browser) => {
        browser
            .openMainMenuEntry('#/sw/catalog/index', 'Catalogues')
            .waitForElementPresent('.sw-catalog-list__intro')
            .waitForElementPresent('.sw-catalog-list__edit-action')
            .click('.sw-catalog-list__edit-action')
            .waitForElementPresent('input[name=sw-field--addCategoryName]')
            .getLocationInView('.sw-catalog-detail__categories')
            .fillField('input[name=sw-field--addCategoryName]', 'MainCategory')
            .click('.sw-catalog-detail__add-action')
            .waitForElementPresent('.sw-tree-item__label')
            .assert.containsText('.sw-tree-item__label', 'MainCategory')
            .click('.sw-button--primary')
            .waitForElementNotPresent('.sw-catalog-detail__properties .sw-card__content .sw-loader')
            .click('.sw-alert button.sw-alert__close')
            .waitForElementNotPresent('.sw-alert__message');
    },
    'open product listing': (browser) => {
        browser
            .assert.containsText('.sw-admin-menu__navigation-list-item.sw-product span.collapsible-text', 'Products')
            .click('a.sw-admin-menu__navigation-link[href="#/sw/product/index"]')
            .waitForElementVisible('.smart-bar__actions a')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(0)');
    },
    'go to create page, fill and save the new product': (browser) => {
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
    'check if the data of the product is assigned correctly': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .refresh()
            .waitForElementVisible('.sw-product-list__column-product-name')
            .assert.containsText('.sw-product-list__column-product-name', 'First one');
    },
    'delete created product and verify deletion': (browser) => {
        const page = productPage(browser);
        page.deleteProduct('First one');
    },
    after: (browser) => {
        browser.end();
    }
};
