const salesChannelPage = require('administration/page-objects/module/sw-sales-channel.page-object.js');
const customerPage = require('administration/page-objects/module/sw-customer.page-object.js');

module.exports = {
    '@tags': ['sales-channel-create', 'sales-channel', 'create'],
    'open sales channel creation': (browser) => {
        browser.expect.element('.sw-admin-menu__headline').to.have.text.that.contains('Sales channel');

        browser
            .click('.sw-admin-menu__headline-action')
            .expect.element('.sw-sales-channel-modal__title').to.have.text.that.contains('Add sales channel');
    },
    'show details of a storefront sales channel': (browser) => {
        const page = salesChannelPage(browser);

        browser
            .expect.element(`${page.elements.gridRow}--0 .sw-sales-channel-modal__grid-item-name`).to.have.text.that.contains('Storefront');

        browser
            .click(`${page.elements.gridRow}--0 .sw-sales-channel-modal__show-detail-action`)
            .expect.element('.sw-sales-channel-modal__title').to.have.text.that.contains('Details of Storefront');
    },
    'open module to add new storefront sales channel': (browser) => {
        const page = salesChannelPage(browser);

        browser
            .click('.sw-sales-channel-modal__add-sales-channel-action')
            .expect.element(`.sw-card:nth-of-type(1) ${page.elements.cardTitle}`).to.have.text.that.contains('General Settings');
        browser.assert.urlContains('#/sw/sales/channel/create');
    },
    'fill in form and save new sales channel': (browser) => {
        const page = salesChannelPage(browser);
        page.createBasicSalesChannel('1st Epic Sales Channel');
    },
    'verify creation and check if the data of the sales channel is assigned correctly': (browser) => {
        const page = salesChannelPage(browser);

        browser
            .refresh();

        page.openSalesChannel('1st Epic Sales Channel');
        browser
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.salesChannelNameInput).to.have.value.that.equals('1st Epic Sales Channel');
    },
    'check if the sales channel can be used in other modules': (browser) => {
        const customerPageObject = customerPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/customer/index',
                mainMenuId: 'sw-customer'
            })
            .click('.smart-bar__actions a[href="#/sw/customer/create"]')
            .fillSelectField('select[name=sw-field--customer-salesChannelId]', '1st Epic Sales Channel');
    },
    after: (browser) => {
        browser.end();
    }
};
