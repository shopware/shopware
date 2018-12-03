const salesChannelPage = require('administration/page-objects/sw-sales-channel.page-object.js');

module.exports = {
    '@tags': ['sales-channel-api-credentials', 'sales-channel', 'api-credentials'],
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
        page.createBasicSalesChannel('3rd Epic Sales Channel');
    },
    'verify creation of new sales channel': (browser) => {
        browser
            .waitForElementNotPresent('.sw-loader')
            .waitForElementVisible('.sw-admin-menu__sales-channel-item .collapsible-text')
            .assert.containsText('.sw-admin-menu__sales-channel-item .collapsible-text', '3rd Epic Sales Channel');
    },
    'edit api credentials': (browser) => {
        browser
            .waitForElementVisible('.sw-admin-menu__sales-channel-item:first-child')
            .click('.sw-admin-menu__sales-channel-item:first-child')
            .waitForElementVisible('.smart-bar__header');

        const page = salesChannelPage(browser);
        page.checkClipboard();
        page.changeApiCredentials('3rd Epic Sales Channel');
    },
    'check if the api credentials of the sales channel are changed correctly': (browser) => {
        browser
            .refresh();

        const page = salesChannelPage(browser);
        page.openSalesChannel('3rd Epic Sales Channel');
        browser
            .waitForElementNotPresent('.sw-loader');
        page.verifyChangedApiCredentials();
    },
    'delete sales channel': (browser) => {
        const page = salesChannelPage(browser);
        page.deleteSingleSalesChannel('3rd Epic Sales Channel');
    },
    after: (browser) => {
        browser.end();
    }
};
