const shippingMethodPage = require('administration/page-objects/module/sw-shipping-method.page-object.js');

const shippingMethodName = 'automated test shipping';

module.exports = {
    '@tags': ['settings', 'shipping-method', 'shipping-method-edit', 'edit', 'price-rule'],
    before: (browser, done) => {
        global.AdminFixtureService.create('rule').then(() => {
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
    'create test data and edit right after initial save': browser => {
        const page = shippingMethodPage(browser);

        browser
            .click('a[href="#/sw/settings/shipping/create"]')
            .waitForElementVisible('.sw-settings-shipping-detail');

        page.createShippingMethod(shippingMethodName);
        browser.waitForElementNotPresent('.icon--small-default-checkmark-line-medium');
        page.fillLoremIpsumIntoSelector('textarea[name=sw-field--shippingMethod-description]', true);

        browser
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
            .click(page.elements.shippingSaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium');
    },
    'lorem text stays filled in after reload': browser => {
        browser
            .refresh()
            .waitForElementVisible('textarea[name=sw-field--shippingMethod-description]')
            .expect.element('textarea[name=sw-field--shippingMethod-description]').to.have.value.that.contains('Lorem ipsum');
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
            .expect.element('.sw-settings-shipping-price-matrix__confirm-delete-text').to.have.text.that.contains('Are you sure you really want to delete the price matrix?');

        browser
            .waitForElementVisible('span.sw-button__content')
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent(page.elements.modal)
            .assert.elementNotPresent('.sw-settings-shipping-price-matrix');
    }
};
