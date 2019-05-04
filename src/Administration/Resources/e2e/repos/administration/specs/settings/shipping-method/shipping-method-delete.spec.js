const shippingMethodPage = require('administration/page-objects/module/sw-shipping-method.page-object.js');

const shippingMethodName = 'automated test shipping';

module.exports = {
    '@tags': ['settings', 'shipping-method', 'shipping-method-delete', 'delete'],
    'navigate to shipping page': browser => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-shipping')
            .assert.urlContains('#/sw/settings/shipping/index');
    },
    'create test data and find in frontend': browser => {
        const page = shippingMethodPage(browser);

        browser
            .click('a[href="#/sw/settings/shipping/create"]')
            .waitForElementVisible('.sw-settings-shipping-detail');

        page.createShippingMethod(shippingMethodName);
        page.moveToListViewFromDetail();

        browser
            .fillGlobalSearchField(shippingMethodName)
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementPresent(page.elements.smartBarAmount);

        browser.expect.element(page.elements.smartBarAmount).to.have.text.that.contains('(1)');
        browser.expect.element(`${page.elements.dataGridRow}--0`).to.have.text.that.contains(shippingMethodName);
    },
    'remove created test data from list view': browser => {
        const page = shippingMethodPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${page.elements.dataGridRow}--0`
            })
            .expect.element('.sw-settings-shipping-list__confirm-delete-text').to.have.text.that.contains(`Are you sure you really want to delete the shipping method "${shippingMethodName}"?`);

        browser
            .waitForElementVisible('span.sw-button__content')
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent(page.elements.modal)
            .assert.elementNotPresent(`${page.elements.dataGridRow}--0`);
    }
};
