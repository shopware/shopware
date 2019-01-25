const salesChannelPage = require('administration/page-objects/module/sw-sales-channel.page-object.js');
const customerPage = require('administration/page-objects/module/sw-customer.page-object.js');

module.exports = {
    '@tags': ['sales-channel-create', 'sales-channel', 'create'],
    'open sales channel creation': (browser) => {
        const page = salesChannelPage(browser);

        browser
            .waitForElementVisible('.sw-admin-menu__headline')
            .assert.containsText('.sw-admin-menu__headline', 'Sales channel')
            .waitForElementVisible('.sw-admin-menu__headline-action')
            .click('.sw-admin-menu__headline-action')
            .waitForElementVisible(page.elements.salesChannelModal)
            .waitForElementVisible('.sw-sales-channel-modal__title')
            .assert.containsText('.sw-sales-channel-modal__title', 'Add sales channel');
    },
    'show details of a storefront sales channel': (browser) => {
        const page = salesChannelPage(browser);

        browser
            .waitForElementVisible('.sw-sales-channel-modal__grid-item-name:first-child')
            .assert.containsText('.sw-sales-channel-modal__grid-item-name:first-child', 'Storefront')
            .waitForElementVisible('.sw-sales-channel-modal__show-detail-action')
            .click('.sw-sales-channel-modal__show-detail-action')
            .waitForElementVisible('.sw-sales-channel-modal__title')
            .assert.containsText('.sw-sales-channel-modal__title', 'Details of Storefront');
    },
    'open module to add new storefront sales channel': (browser) => {
        const page = salesChannelPage(browser);

        browser
            .waitForElementVisible('.sw-sales-channel-modal__add-sales-channel-action')
            .click('.sw-sales-channel-modal__add-sales-channel-action')
            .waitForElementVisible(page.elements.cardTitle)
            .waitForElementVisible('.sw-sales-channel-detail-base')
            .assert.urlContains('#/sw/sales/channel/create')
            .assert.containsText(page.elements.cardTitle, 'General Settings');
    },
    'fill in form and save new sales channel': (browser) => {
        const page = salesChannelPage(browser);
        page.createBasicSalesChannel('1st Epic Sales Channel');
    },
    'verify creation and check if the data of the sales channel is assigned correctly': (browser) => {
        const page = salesChannelPage(browser);

        browser
            .refresh();

        page.openSalesChannel('1st Epic Sales Channel');
        browser
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible('input[name=sw-field--salesChannel-name]')
            .expect.element(page.elements.salesChannelNameInput).to.have.value.that.equals('1st Epic Sales Channel');
    },
    'check if the sales channel can be used in other modules': (browser) => {
        const customerPageObject = customerPage(browser);

        browser
            .openMainMenuEntry('#/sw/customer/index', 'Customers')
            .waitForElementPresent('.smart-bar__actions a[href="#/sw/customer/create"]')
            .click('.smart-bar__actions a[href="#/sw/customer/create"]')
            .waitForElementVisible(customerPageObject.elements.customerForm)
            .fillSelectField('select[name=sw-field--customer-salesChannelId]', '1st Epic Sales Channel');
    },
    after: (browser) => {
        browser.end();
    }
};
