const shippingMethodPage = require('administration/page-objects/module/sw-shipping-method.page-object.js');

const shippingMethodName = 'automated test shipping';

module.exports = {
    '@tags': ['settings', 'shipping', 'edit', 'shipping-price-rule', 'rule'],
    before: (browser, done) => {
        return global.ShippingFixtureService.setShippingFixture({
            name: shippingMethodName
        }).then(() => {
            done();
        });
    },
    'navigate to shipping page': browser => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-shipping')
            .assert.urlContains('#/sw/settings/shipping/index');
    },
    'find shipping method to be edited': browser => {
        const page = shippingMethodPage(browser);

        browser
            .fillGlobalSearchField(shippingMethodName)
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementPresent(page.elements.smartBarAmount);

        browser.expect.element(`${page.elements.dataGridRow}--0`).to.have.text.that.contains(shippingMethodName);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-settings-shipping-list__edit-action',
                scope: `${page.elements.dataGridRow}--0`
            })
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains(shippingMethodName);
    },
    'add price rule': browser => {
        const page = shippingMethodPage(browser);

        browser
            .getLocationInView('.sw-settings-shipping-price-matrices__actions')
            .fillSwSelectComponent('.sw-settings-shipping-price-matrix__top-container .sw-select-rule-create', {
                value: 'Cart >= 0',
                searchTerm: 'Cart >= 0'
            });

        page.createShippingMethodPriceRule();
    },
    'edit shippingMethod price rule': browser => {
        const page = shippingMethodPage(browser);
        browser
            .getLocationInView('.sw-settings-shipping-price-matrices__actions')
            .waitForElementVisible(`${page.elements.dataGridRow}--0`)
            .moveToElement(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--quantityStart`, 5, 5)
            .doubleClick()
            .waitForElementPresent('.is--inline-edit')
            .fillField(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--price input`, '10.5', false)
            .click('.sw-data-grid__inline-edit-save')
            .waitForElementNotPresent('.sw-data-grid__inline-edit-save')
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
            .click(page.elements.shippingSaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium')
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium');
    },
    'delete shippingMethod price rule': browser => {
        const page = shippingMethodPage(browser);

        browser
            .getLocationInView('.sw-settings-shipping-price-matrices__actions')
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${page.elements.dataGridRow}--1`
            })
            .assert.elementNotPresent(`${page.elements.dataGridRow}--1`)
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: '.sw-settings-shipping-price-matrix__top-container'
            })
            .expect.element('.sw-settings-shipping-price-matrix__confirm-delete-text').to.have.text.that.contains('Are you sure you really want to delete the price matrix "Cart >= 0"?');

        browser
            .waitForElementVisible('span.sw-button__content')
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent(page.elements.modal)
            .assert.elementNotPresent('.sw-settings-shipping-price-matrix');
    }
};
