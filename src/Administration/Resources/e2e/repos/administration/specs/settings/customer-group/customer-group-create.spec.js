const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');
const salesChannelPage = require('administration/page-objects/module/sw-sales-channel.page-object.js');

module.exports = {
    '@tags': ['settings', 'customer-group-create', 'customer-group', 'create'],
    'open currency module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-customer-group')
            .assert.urlContains('#/sw/settings/customer/group/index');
    },
    'create new customer group': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('a[href="#/sw/settings/customer/group/create"]')
            .waitForElementVisible('.sw-settings-customer-group-detail .sw-card__content')
            .expect.element(page.elements.customerGroupSaveAction).to.not.be.enabled;

        browser
            .assert.urlContains('#/sw/settings/customer/group/create')
            .fillField('input[name=sw-field--customerGroup-name]', 'E2E Merchant')
            .tickCheckbox('input[name=sw-field--customerGroup-displayGross]', true)
            .expect.element(page.elements.customerGroupSaveAction).to.be.enabled;

        browser.click(page.elements.customerGroupSaveAction)
            .checkNotification('Customer group "E2E Merchant" has been saved successfully.')
            .assert.urlContains('#/sw/settings/customer/group/detail');
    },
    'go back to listing and verify creation': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .refresh()
            .expect.element(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).to.have.text.that.contains('E2E Merchant');
    },
    'check if customer group can be used in customer module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/customer/index',
                mainMenuId: 'sw-customer'
            })
            .click('.smart-bar__actions a[href="#/sw/customer/create"]')
            .fillSelectField('select[name=sw-field--customer-groupId]', 'E2E Merchant');
    },
    'check if customer group can be used in sales channel module': (browser) => {
        const page = salesChannelPage(browser);

        browser.expect.element(
            `${page.elements.salesChannelMenuName}--1 .collapsible-text`
        ).to.have.text.that.contains('Storefront API');

        browser
            .click(`${page.elements.salesChannelMenuName}--1`)
            .fillSwSelectComponent(
                '.sw-sales-channel-detail__select-customer-group',
                {
                    value: 'E2E Merchant',
                    isMulti: false,
                    searchTerm: 'E2E Merchant'
                }
            );
    }
};
