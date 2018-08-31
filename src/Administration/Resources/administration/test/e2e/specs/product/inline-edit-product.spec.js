module.exports = {
    '@tags': ['product-inline-edit'],
    'open product listing': (browser) => {
        browser
            .assert.containsText('.sw-admin-menu__navigation-list-item.sw-product span.collapsible-text', 'Products')
            .click('a.sw-admin-menu__navigation-link[href="#/sw/product/index"]')
            .waitForElementVisible('.smart-bar__actions a')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(0)');
    },
    'create the product': (browser) => {
        browser
            .click('a[href="#/sw/product/create"]')
            .waitForElementVisible('.sw-product-detail-base')
            .assert.urlContains('#/sw/product/create')
            .assert.containsText('.sw-card__title', 'Product information')
            .setValue('input[name=sw-field--product-name]', 'First one')
            .setValue('select[name=sw-field--product-manufacturerId]', 'shopware AG')
            .setValue('select[name=sw-field--product-catalogId]', 'Default catalogue')
            .setValue('select[name=sw-field--product-taxId]', '19%')
            .setValue('input[name=sw-field--price-gross]', '99')
            .click('.smart-bar__actions button.sw-button--primary')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert__message', 'The product "First one" was saved successfully.')
            .assert.urlContains('#/sw/product/detail');
    },
    'go back to listing': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .useXpath()
            .waitForElementPresent('//a[contains(text(), "First one")]')
            .useCss()
            .click('.sw-alert button.sw-alert__close')
            .waitForElementNotPresent('.sw-alert__message');
    },
    'edit product name via inline editing and verify change': (browser) => {
        browser
            .moveToElement('.sw-grid-row:first-child', 0, 0).doubleClick()
            .clearValue('input[name=sw-field--item-name]')
            .setValue('input[name=sw-field--item-name]', 'Second one')
            .useXpath()
            .waitForElementVisible("//span[contains(text(), 'Save')]")
            .click('//span[contains(text(), "Save")]')
            .useCss()
            .assert.containsText('.sw-grid-row:first-child .sw-grid-column a', 'Second one');
    },
    'delete created product': (browser) => {
        browser
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .click('.sw-grid-row:first-child .sw-context-button__button')
            .waitForElementPresent('body > .sw-context-menu')
            .click('body > .sw-context-menu .sw-context-menu-item--danger')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-product-list__confirm-delete-text', 'Do you really want to delete the product "Second one"?')
            .click('.sw-modal__footer button.sw-button--primary')
            .useXpath()
            .click('//span[contains(text(), "Save")]')
            .waitForElementNotPresent('//a[contains(text(), "Second one")]')
            .end();
    }
};
