const salesChannelPage = require('administration/page-objects/sw-sales-channel.page-object.js');

module.exports = {
    '@tags': ['sales-channel-create', 'sales-channel', 'create'],
    'open sales channel creation': (browser) => {
        browser
            .waitForElementVisible('.sw-admin-menu__headline')
            .assert.containsText('.sw-admin-menu__headline', 'Sales channel')
            .waitForElementVisible('.sw-admin-menu__headline-action')
            .click('.sw-admin-menu__headline-action')
            .waitForElementVisible('.sw-sales-channel-modal')
            .waitForElementVisible('.sw-sales-channel-modal__title')
            .assert.containsText('.sw-sales-channel-modal__title', 'Add sales channel');
    },
    'show details of a storefront sales channel': (browser) => {
        browser
            .waitForElementVisible('.sw-sales-channel-modal__grid-item-name:first-child')
            .assert.containsText('.sw-sales-channel-modal__grid-item-name:first-child', 'Storefront')
            .waitForElementVisible('.sw-sales-channel-modal__show-detail-action')
            .click('.sw-sales-channel-modal__show-detail-action')
            .waitForElementVisible('.sw-sales-channel-modal__title')
            .assert.containsText('.sw-sales-channel-modal__title', 'Details of Storefront');
    },
    'open module to add new storefront sales channel': (browser) => {
        browser
            .waitForElementVisible('.sw-sales-channel-modal__add-sales-channel-action')
            .click('.sw-sales-channel-modal__add-sales-channel-action')
            .waitForElementVisible('.sw-card__title')
            .waitForElementVisible('.sw-sales-channel-detail-base')
            .assert.urlContains('#/sw/sales/channel/create')
            .assert.containsText('.sw-card__title', 'General Settings');
    },
    'fill in form and save new sales channel': (browser) => {
        const page = salesChannelPage(browser);
        page.createBasicSalesChannel('1st Epic Sales Channel');
    },
    'verify creation and check if the data of the sales channel is assigned correctly': (browser) => {
        browser
            .refresh();
        const page = salesChannelPage(browser);
        page.openSalesChannel('1st Epic Sales Channel');
        browser
            .expect.element('input[name=sw-field--salesChannel-name]').to.have.value.that.equals('1st Epic Sales Channel');
    },
    'check if the sales channel can be used in other modules': (browser) => {
        browser
            .openMainMenuEntry('#/sw/customer/index', 'Customers', '#/sw/customer/create', 'Add customer')
            .fillSelectField('select[name=sw-field--customer-salesChannelId]', '1st Epic Sales Channel');
    },
    'delete sales channel': (browser) => {
        const page = salesChannelPage(browser);
        page.openSalesChannel('1st Epic Sales Channel');
        page.deleteSingleSalesChannel('1st Epic Sales Channel');
    },
    after: (browser) => {
        browser.end();
    }
};
