const salesChannelPage = require('administration/page-objects/sw-sales-channel.page-object.js');
let salesChannelFixture = global.FixtureService.loadJson('sales-channel.json');

module.exports = {
    '@tags': ['sales-channel-delete', 'sales-channel', 'delete'],
    before: (browser, done) => {
        salesChannelFixture.name = '1st Epic Sales Channel';
        global.SalesChannelFixtureService.setSalesChannelFixture(salesChannelFixture, done);
    },
    'delete sales channel': (browser) => {
        browser.refresh();
        const page = salesChannelPage(browser);
        page.openSalesChannel(salesChannelFixture.name);
        page.deleteSingleSalesChannel(salesChannelFixture.name);
    },
    after: (browser) => {
        browser.end();
    }
};
