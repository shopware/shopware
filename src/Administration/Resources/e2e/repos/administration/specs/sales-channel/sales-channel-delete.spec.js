const salesChannelPage = require('administration/page-objects/module/sw-sales-channel.page-object.js');

const fixture = {
    name: '1st Epic Sales Channel',
    accessKey: global.FixtureService.createUuid()
};

module.exports = {
    '@tags': ['sales-channel-delete', 'sales-channel', 'delete'],
    before: (browser, done) => {
        global.SalesChannelFixtureService.setSalesChannelFixture(fixture).then(() => {
            done();
        });
    },
    'delete sales channel': (browser) => {
        browser.refresh();
        const page = salesChannelPage(browser);
        page.openSalesChannel(fixture.name);
        page.deleteSingleSalesChannel(fixture.name);
    },
    after: (browser) => {
        browser.end();
    }
};
