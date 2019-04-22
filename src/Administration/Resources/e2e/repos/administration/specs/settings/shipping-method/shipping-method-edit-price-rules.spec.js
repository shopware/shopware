const shippingMethodPage = require('administration/page-objects/module/sw-shipping-method.page-object.js');

const shippingMethodName = 'automated test shipping';

module.exports = {
    '@tags': ['settings', 'shipping-method', 'shipping-method-edit', 'edit', 'price-rule'],
    '@disabled': !global.flags.isActive('next688'),
    before: (browser, done) => {
        global.AdminFixtureService.create('rule').then(() => {
            done();
        });
    },
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
    },
    'create shippingMethod price rule': browser => {
        const page = shippingMethodPage(browser);
        page.createShippingMethodPriceRule(shippingMethodName);
    },
    'edit shippingMethod price rule': browser => {
        const page = shippingMethodPage(browser);
        browser
            .click('div[name=calculation]')
            .waitForElementVisible('.sw-select__results-list')
            .click('.sw-select__results-list .sw-select-option--1')
            .clearValue(`.context-prices__prices ${page.elements.gridRow}--1 input[name=sw-field--item-price]`)
            .fillField(`.context-prices__prices ${page.elements.gridRow}--1 input[name=sw-field--item-price]`, '9')
            .click(page.elements.shippingSaveAction)
            .checkNotification(`Shipping rate "${shippingMethodName}" has been saved successfully.`);
    },
    'delete shippingMethod price rule': browser => {
        const page = shippingMethodPage(browser);

        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', )
            .clickContextMenuItem('.sw-context-menu-item--danger', {
                menuActionSelector: `${page.elements.gridRow}--1 ${page.elements.contextMenuButton}`
            })
            .assert.elementNotPresent(`${page.elements.gridRow}--1`)
            .click('.sw-settings-shipping-detail__delete-action')
            .assert.elementNotPresent('.context-price');
    }
};
