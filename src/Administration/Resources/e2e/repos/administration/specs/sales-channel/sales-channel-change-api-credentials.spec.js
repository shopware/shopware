const salesChannelPage = require('administration/page-objects/sw-sales-channel.page-object.js');

const fixture = {
    name: '3rd Epic Sales Channel',
    accessKey: global.FixtureService.createUuid()
};

module.exports = {
    '@tags': ['sales-channel-api-credentials', 'sales-channel', 'api-credentials'],
    before: (browser, done) => {
        global.SalesChannelFixtureService.setSalesChannelFixture(fixture).then(() => {
            done();
        });
    },
    'verify existence of sales channel to be edited': (browser) => {
        browser
            .refresh()
            .waitForElementVisible('.sw-admin-menu__sales-channel-item .collapsible-text')
            .assert.containsText('.sw-admin-menu__sales-channel-item .collapsible-text', fixture.name);
    },
    'edit api credentials': (browser) => {
        browser
            .waitForElementVisible('.sw-admin-menu__sales-channel-item:first-child')
            .click('.sw-admin-menu__sales-channel-item:first-child')
            .waitForElementVisible('.smart-bar__header');

        const page = salesChannelPage(browser);
        page.checkClipboard();
        page.changeApiCredentials(fixture.name);
    },
    'check if the api credentials of the sales channel are changed correctly': (browser) => {
        browser
            .refresh();

        const page = salesChannelPage(browser);
        page.openSalesChannel(fixture.name);
        browser
            .waitForElementNotPresent('.sw-loader');
        page.verifyChangedApiCredentials();
    },
    after: (browser) => {
        browser.end();
    }
};
