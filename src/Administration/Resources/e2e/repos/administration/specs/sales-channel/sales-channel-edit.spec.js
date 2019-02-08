const salesChannelPage = require('administration/page-objects/module/sw-sales-channel.page-object.js');

const userFixture = {
    name: '2nd Epic Sales Channel',
    accessKey: global.AdminFixtureService.createUuid()
};

module.exports = {
    '@tags': ['sales-channel-edit', 'sales-channel', 'edit'],
    before: (browser, done) => {
        global.SalesChannelFixtureService.setSalesChannelFixture(userFixture).then(() => {
            done();
        });
    },
    'verify creation of sales channel to be edited': (browser) => {
        const page = salesChannelPage(browser);

        browser
            .refresh()
            .waitForElementVisible(`${page.elements.salesChannelMenuName}--0`)
            .assert.containsText(`${page.elements.salesChannelMenuName}--0`, userFixture.name);
    },
    'edit name of sales channel': (browser) => {
        const page = salesChannelPage(browser);

        browser
            .waitForElementVisible(`${page.elements.salesChannelMenuName}--0`)
            .click(`${page.elements.salesChannelMenuName}--0`)
            .waitForElementVisible(page.elements.smartBarHeader)
            .assert.containsText(`${page.elements.smartBarHeader} h2`, userFixture.name)
            .fillField(page.elements.salesChannelNameInput, '2nd Epic Sales Channel at all', true)
            .waitForElementVisible(page.elements.salesChannelSaveAction)
            .click(page.elements.salesChannelSaveAction);
    },
    'check if the data of the sales channel is assigned correctly': (browser) => {
        const page = salesChannelPage(browser);

        browser
            .refresh();

        page.openSalesChannel('2nd Epic Sales Channel at all');
        browser
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(page.elements.salesChannelNameInput)
            .expect.element(page.elements.salesChannelNameInput).to.have.value.that.equals('2nd Epic Sales Channel at all');
    },
    after: (browser) => {
        browser.end();
    }
};
