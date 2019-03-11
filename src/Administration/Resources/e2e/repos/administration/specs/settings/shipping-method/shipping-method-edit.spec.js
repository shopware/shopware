const shippingMethodPage = require('administration/page-objects/module/sw-shipping-method.page-object.js');

const shippingMethodName = 'automated test shipping';

module.exports = {
    '@tags': ['settings', 'shipping-method', 'shipping-method-edit', 'edit'],
    '@disabled': !global.flags.isActive('next688'),
    'navigate to shipping page': browser => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/shipping/index',
                mainMenuId: 'sw-settings',
                subMenuId: 'sw-settings-shipping'
            });
    },
    'create test data and edit right after initial save': browser => {
        const page = shippingMethodPage(browser);

        browser
            .click('a[href="#/sw/settings/shipping/create"]')
            .waitForElementVisible('.sw-settings-shipping-detail');

        page.createShippingMethod(shippingMethodName);
        page.fillLoremIpsumIntoSelector('textarea[name=sw-field--shippingMethod-description]', true);

        browser
            .click(page.elements.shippingSaveAction)
            .checkNotification(`Shipping rate "${shippingMethodName}" has been saved successfully.`);
    },
    'lorem text stays filled in after reload': browser => {
        browser
            .refresh()
            .waitForElementVisible('textarea[name=sw-field--shippingMethod-description]')
            .expect.element('textarea[name=sw-field--shippingMethod-description]').to.have.value.that.contains('Lorem ipsum');
    }
};
