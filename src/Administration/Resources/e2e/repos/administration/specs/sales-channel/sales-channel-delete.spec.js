const salesChannelPage = require('administration/page-objects/module/sw-sales-channel.page-object.js');

const fixture = {
    name: '1st Epic Sales Channel',
    accessKey: global.AdminFixtureService.createUuid()
};

module.exports = {
    '@tags': ['sales-channel-delete', 'sales-channel', 'delete'],
    before: (browser, done) => {
        global.AdminSalesChannelFixtureService.setSalesChannelFixture(fixture).then(() => {
            done();
        });
    },
    'delete sales channel': (browser) => {
        browser
            .refresh()
            .waitForElementVisible('.sw-admin-menu__sales-channel-item--0');

        const page = salesChannelPage(browser);
        page.openSalesChannel(fixture.name);
        page.deleteSingleSalesChannel(fixture.name);
    }
};
