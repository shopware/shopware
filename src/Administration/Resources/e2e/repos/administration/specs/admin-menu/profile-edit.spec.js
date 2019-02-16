const loginPage = require('administration/page-objects/module/sw-login.page-object.js');

module.exports = {
    '@tags': ['profile-edit', 'profile', 'edit'],
    'open user profile and edit values': (browser) => {
        const page = loginPage(browser);

        browser
            .waitForElementVisible('.sw-dashboard-index__content')
            .clickUserActionMenu('admin')
            .click('.sw-admin-menu__profile-item')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals('Your profile');

        browser
            .fillField('input[name=sw-field--user-name]', 'Super Richie', true)
            .click(page.elements.primaryButton)
            .checkNotification('Profile information has been saved successfully.')
            .expect.element('.sw-admin-menu__user-name').to.have.text.that.equals('Super Richie');
    },
    'log out': (browser) => {
        const page = loginPage(browser);
        page.logout('Super Richie');
    },
    'log in user with updated credentials': (browser) => {
        const page = loginPage(browser);
        page.login('admin', 'shopware');
    },
    'verify login with new credentials': (browser) => {
        const page = loginPage(browser);
        page.verifyLogin('Super Richie');
    },
    'verify other changed data': (browser) => {
        browser
            .waitForElementVisible('.sw-dashboard-index__content')
            .clickUserActionMenu('Super Richie')
            .click('.sw-admin-menu__profile-item')
            .expect.element('input[name=sw-field--user-name]').to.have.value.that.equals('Super Richie');
    },
    after: (browser) => {
        browser.end();
    }
};
