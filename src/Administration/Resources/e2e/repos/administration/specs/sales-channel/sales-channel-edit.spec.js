const salesChannelPage = require('administration/page-objects/sw-sales-channel.page-object.js');

module.exports = {
    '@tags': ['sales-channel-edit', 'sales-channel', 'edit'],
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
    'open form to add sales channel': (browser) => {
        browser
            .waitForElementVisible('.sw-sales-channel-modal__grid-item-name:first-child')
            .assert.containsText('.sw-sales-channel-modal__grid-item-name:first-child', 'Storefront')
            .waitForElementVisible('.sw-sales-channel-modal__add-channel-action')
            .click('.sw-sales-channel-modal__add-channel-action')
            .waitForElementNotPresent('.sw-sales-channel-modal')
            .waitForElementVisible('.sw-sales-channel-detail-base')
            .assert.containsText('.sw-card__title', 'General Settings');
    },
    'fill in form and add new sales channel': (browser) => {
        const page = salesChannelPage(browser);
        page.createBasicSalesChannel('2nd Epic Sales Channel');
    },
    'verify creation of new sales channel': (browser) => {
        browser
            .waitForElementNotPresent('.sw-loader')
            .waitForElementVisible('.sw-admin-menu__sales-channel-item .collapsible-text')
            .assert.containsText('.sw-admin-menu__sales-channel-item .collapsible-text', '2nd Epic Sales Channel');
    },
    'edit name of sales channel': (browser) => {
        browser
            .waitForElementVisible('.sw-admin-menu__sales-channel-item:first-child')
            .click('.sw-admin-menu__sales-channel-item:first-child')
            .waitForElementVisible('.smart-bar__header')
            .assert.containsText('.smart-bar__header h2', '2nd Epic Sales Channel')
            .fillField('input[name=sw-field--salesChannel-name]', '2nd Epic Sales Channel at all')
            .waitForElementVisible('.sw-sales-channel-detail__save-action')
            .click('.sw-sales-channel-detail__save-action');
    },
    'check if the data of the sales channel is assigned correctly': (browser) => {
        browser
            .refresh();
        const page = salesChannelPage(browser);
        page.openSalesChannel('2nd Epic Sales Channel at all');
        browser
            .waitForElementNotPresent('.sw-loader')
            .waitForElementVisible('input[name=sw-field--salesChannel-name]')
            .expect.element('input[name=sw-field--salesChannel-name]').to.have.value.that.equals('2nd Epic Sales Channel at all');
    },
    after: (browser) => {
        browser.end();
    }
};
