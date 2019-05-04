const shippingMethodPage = require('administration/page-objects/module/sw-shipping-method.page-object.js');

const shippingMethodName = 'automated test shipping';

module.exports = {
    '@tags': ['settings', 'shipping-method', 'shipping-method-create'],
    'navigate to shipping method index': browser => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-shipping')
            .assert.urlContains('#/sw/settings/shipping/index');
    },
    'create shippingMethod': browser => {
        const page = shippingMethodPage(browser);

        browser
            .click('a[href="#/sw/settings/shipping/create"]')
            .waitForElementVisible('.sw-settings-shipping-detail .sw-card__content')
            .assert.urlContains('#/sw/settings/shipping/create')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('New shipping method');

        page.createShippingMethod(shippingMethodName);
    },
    'find new shippingMethod': browser => {
        const page = shippingMethodPage(browser);
        page.moveToListViewFromDetail();

        browser
            .fillGlobalSearchField(shippingMethodName)
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementPresent(page.elements.smartBarAmount);

        browser.expect.element(page.elements.smartBarAmount).to.have.text.that.contains('(1)');
        browser.expect.element(`${page.elements.dataGridRow}--0`).to.have.text.that.contains(shippingMethodName);
    }
};
