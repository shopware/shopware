const salesChannelPage = require('administration/page-objects/module/sw-sales-channel.page-object.js');

const fixture = {
    name: '3rd Epic Sales Channel',
    accessKey: global.AdminFixtureService.createUuid()
};

module.exports = {
    '@tags': ['sales-channel-api-credentials', 'edit', 'sales-channel', 'api-credentials'],
    before: (browser, done) => {
        global.AdminSalesChannelFixtureService.setSalesChannelFixture(fixture).then(() => {
            done();
        });
    },
    'verify existence of sales channel to be edited': (browser) => {
        const page = salesChannelPage(browser);

        browser
            .refresh()
            .expect.element(`${page.elements.salesChannelMenuName}--0 .collapsible-text`).to.have.text.that.contains(fixture.name);
    },
    'edit api credentials': (browser) => {
        const page = salesChannelPage(browser);

        browser.click(`${page.elements.salesChannelMenuName}--0`);

        page.checkClipboard();
        page.changeApiCredentials(fixture.name);
    },
    'check if the api credentials of the sales channel are changed correctly': (browser) => {
        const page = salesChannelPage(browser);

        browser.refresh();
        page.openSalesChannel(fixture.name);
        browser.waitForElementNotPresent(page.elements.loader);
        page.verifyChangedApiCredentials();
    }
};
