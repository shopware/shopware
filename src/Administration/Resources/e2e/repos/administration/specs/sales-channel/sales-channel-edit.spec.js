const salesChannelPage = require('administration/page-objects/sw-sales-channel.page-object.js');

const userFixture = {
    name: '2nd Epic Sales Channel',
    accessKey: global.FixtureService.createUuid()
};

module.exports = {
    '@tags': ['sales-channel-edit', 'sales-channel', 'edit'],
    before: (browser, done) => {
        global.SalesChannelFixtureService.setSalesChannelFixture(userFixture).then(() => {
            done();
        });
    },
    'verify creation of sales channel to be edited': (browser) => {
        browser
            .refresh()
            .waitForElementVisible('.sw-admin-menu__sales-channel-item .collapsible-text')
            .assert.containsText('.sw-admin-menu__sales-channel-item .collapsible-text', userFixture.name);
    },
    'edit name of sales channel': (browser) => {
        browser
            .waitForElementVisible('.sw-admin-menu__sales-channel-item:first-child')
            .click('.sw-admin-menu__sales-channel-item:first-child')
            .waitForElementVisible('.smart-bar__header')
            .assert.containsText('.smart-bar__header h2', userFixture.name)
            .fillField('input[name=sw-field--salesChannel-name]', '2nd Epic Sales Channel at all', true)
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
