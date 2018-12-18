const loginPage = require('administration/page-objects/sw-login.page-object.js');
const beforeScenarioActions = require('administration/specs/before-scenario.js');

module.exports = {
    '@tags': ['profile-edit','profile','edit'],
    'open user profile and edit values': (browser) => {
        browser
            .waitForElementVisible('.sw-dashboard-index__content')
            .clickUserActionMenu('admin')
            .waitForElementVisible('.sw-admin-menu__profile-item')
            .click('.sw-admin-menu__profile-item')
            .assert.containsText('.smart-bar__header', 'Your profile')
            .fillField('input[name=sw-field--user-name]', 'Super Richie')
            .fillField('input[name=sw-field--user-email]', 'mail@shopware.com')
            .fillField('input[name=sw-field--newPassword]', 'sicheresPasswort1234')
            .fillField('input[name=sw-field--newPasswordConfirm]', 'sicheresPasswort1234')
            .click('.sw-button--primary')
            .checkNotification('Profile information have been saved successfully.')
            .waitForElementVisible('.sw-admin-menu__user-name')
            .assert.containsText('.sw-admin-menu__user-name', 'Super Richie');
    },
    'log out': (browser) => {
        const page = loginPage(browser);

        beforeScenarioActions.hideToolbarIfVisible(browser);
        page.logout('Super Richie');
    },
    'log in user with updated credentials': (browser) => {
        const page = loginPage(browser);
        page.login('admin', 'sicheresPasswort1234');
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
            .assert.containsText('.smart-bar__header', 'Your profile')
            .expect.element('input[name=sw-field--user-email]').to.have.value.that.equals('mail@shopware.com');
    },
    after: (browser) => {
        browser.end();
    }
};
