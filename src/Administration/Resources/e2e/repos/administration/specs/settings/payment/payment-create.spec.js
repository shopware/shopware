const paymentPage = require('administration/page-objects/module/sw-payment.page-object.js');

module.exports = {
    '@tags': ['settings', 'payment', 'payment-create'],
    'navigate to payment method index': browser => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-payment')
            .assert.urlContains('#/sw/settings/payment/index');
    },
    'tabs can be found more than once': browser => {
        const page = paymentPage(browser);

        browser
            .click('a[href="#/sw/settings/payment/create"]')
            .waitForElementVisible('.sw-settings-payment-detail')
            .assert.urlContains('#/sw/settings/payment/create')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('New Payment method');
    },
    'create payment method': browser => {
        const page = paymentPage(browser);

        page.createPaymentMethod('CredStick');
    },
    'find new payment method': browser => {
        const page = paymentPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .fillField('.sw-search-bar__input', 'CredStick')
            .waitForElementNotPresent(`${page.elements.gridRow}--1`)
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains('CredStick');
    }
};
