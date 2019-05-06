const paymentPage = require('administration/page-objects/module/sw-payment.page-object.js');

module.exports = {
    '@tags': ['settings', 'payment', 'payment-delete'],
    before: (browser, done) => {
        global.AdminFixtureService.create('payment-method').then(() => {
            done();
        });
    },
    'navigate to payment page': browser => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-payment')
            .assert.urlContains('#/sw/settings/payment/index');
    },
    'find payment method to be deleted': browser => {
        const page = paymentPage(browser);

        browser
            .fillGlobalSearchField(global.AdminFixtureService.basicFixture.name)
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(1)');
        browser.expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    },
    'remove created payment method from list view': browser => {
        const page = paymentPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${page.elements.gridRow}--0`
            })
            .expect.element(`${page.elements.modal}__body`).to.have.text.that.equals(`Are you sure you want to delete the payment method "${global.AdminFixtureService.basicFixture.name}"?`);

        browser
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .checkNotification(`Payment method "${global.AdminFixtureService.basicFixture.name}" has been deleted successfully.`);
    }
};
