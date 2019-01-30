const salesChannelPage = require('administration/page-objects/module/sw-sales-channel.page-object.js');

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
        const page = salesChannelPage(browser);

        browser
            .refresh()
            .waitForElementVisible(`${page.elements.salesChannelMenuName} .collapsible-text`)
            .assert.containsText(`${page.elements.salesChannelMenuName} .collapsible-text`, fixture.name);
    },
    'edit api credentials': (browser) => {
        const page = salesChannelPage(browser);

        browser
            .waitForElementVisible(`${page.elements.salesChannelMenuName}--0`)
            .click(`${page.elements.salesChannelMenuName}--0`)
            .waitForElementVisible(page.elements.smartBarHeader);

        page.checkClipboard();
        page.changeApiCredentials(fixture.name);
    },
    'check if the api credentials of the sales channel are changed correctly': (browser) => {
        const page = salesChannelPage(browser);

        browser.refresh();
        page.openSalesChannel(fixture.name);
        browser.waitForElementNotPresent(page.elements.loader);
        page.verifyChangedApiCredentials();
    },
    after: (browser) => {
        browser.end();
    }
};
