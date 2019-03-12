const shippingMethodPage = require('administration/page-objects/module/sw-shipping-method.page-object.js');

const shippingMethodName = 'automated test shipping';

module.exports = {
    '@tags': ['settings', 'shipping-method', 'shipping-method-create'],
    '@disabled': !global.flags.isActive('next688'),
    'navigate to shipping method index': browser => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/shipping/index',
                mainMenuId: 'sw-settings',
                subMenuId: 'sw-settings-shipping'
            });
    },
    'tabs can be found more than once': browser => {
        browser
            .click('a[href="#/sw/settings/shipping/create"]')
            .waitForElementVisible('.sw-settings-shipping-detail')
            .assert.urlContains('#/sw/settings/shipping/create/base')
            .elements('css selector', '.sw-tabs-item', function (result) {
                this.assert.equal(result.value.length > 1, true);
            });
    },
    'create shippingMethod': browser => {
        const page = shippingMethodPage(browser);

        page.createShippingMethod(shippingMethodName);
    },
    'find new shippingMethod': browser => {
        const page = shippingMethodPage(browser);
        page.moveToListViewFromDetail();

        browser
            .fillField('.sw-search-bar__input', shippingMethodName)
            .waitForElementNotPresent(`${page.elements.gridRow}--1`)
            .elements('css selector', '.sw-grid-row', function (result) {
                this.assert.equal(result.value.length === 1, true);
            })
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains(shippingMethodName);
    }
};
