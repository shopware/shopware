const shippingMethodPage = require('administration/page-objects/module/sw-shipping-method.page-object.js');

const shippingMethodName = 'automated test shipping';

module.exports = {
    '@tags': ['settings', 'shipping-method', 'shipping-method-delete', 'delete'],
    'navigate to shipping page': browser => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/shipping/index',
                mainMenuId: 'sw-settings',
                subMenuId: 'sw-settings-shipping'
            });
    },
    'create test data and find in frontend': browser => {
        const page = shippingMethodPage(browser);

        browser
            .click('a[href="#/sw/settings/shipping/create"]')
            .waitForElementVisible('.sw-settings-shipping-detail');

        page.createShippingMethod(shippingMethodName);
        page.moveToListViewFromDetail();

        browser
            .fillField('.sw-search-bar__input', shippingMethodName)
            .waitForElementNotPresent(`${page.elements.gridRow}--1`)
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains(shippingMethodName);
    },
    'remove created test data from list view': browser => {
        const page = shippingMethodPage(browser);

        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', page.elements.contextMenuButton)
            .waitForElementVisible(page.elements.modal)
            .expect.element('.sw-modal__body p').to.have.text.that.contains(`Are you sure you want to delete the shipping method "${shippingMethodName}"?`);

        browser
            .waitForElementVisible('span.sw-button__content')
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent(page.elements.modal)
            .assert.elementNotPresent(`${page.elements.gridRow}--0`);
    }
};
