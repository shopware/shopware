const loginPage = require('administration/page-objects/module/sw-login.page-object.js');

module.exports = {
    '@tags': ['profile-edit', 'profile', 'edit'],
    'open user profile and edit values': (browser) => {
        const page = loginPage(browser);

        browser
            .waitForElementVisible('.sw-dashboard-index__content')
            .waitForElementNotPresent('.sw-admin-menu__user-actions-toggle .sw-loader')
            .openUserActionMenu()
            .click('.sw-admin-menu__profile-item')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals('Your profile');

        browser
            .clearValueManual('input[name=sw-field--user-email]')
            .fillField('input[name=sw-field--user-email]', 'test@test.de', true);

        browser
            .waitForElementVisible('.sw-profile__language')
            .expect.element('select[name=sw-field--user-localeId]').to.have.text.that.contains('English (United Kingdom)');

        browser
            .fillSelectField('select[name=sw-field--user-localeId]', 'German (Germany)')
            .click(page.elements.primaryButton)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium')
            .waitForElementNotPresent(page.elements.loader)
            // ToDo NEXT-3783: Re-enable with fixed language handling
            // .expect.element('select[name=sw-field--user-localeId]').to.have.text.that.contains('Englisch (Vereinigtes Königreich)');
            .expect.element('select[name=sw-field--user-localeId]').to.have.text.that.contains('English (United Kingdom)');
    },
    'log out': (browser) => {
        const page = loginPage(browser);
        browser.openUserActionMenu();
        page.logout('Melde Dich in Deinem Shopware Shop an.');
    },
    'log in user with updated credentials': (browser) => {
        const page = loginPage(browser);
        page.login('admin', 'shopware');
    },
    'verify changed data': (browser) => {
        const page = loginPage(browser);

        browser
            .waitForElementVisible('.sw-dashboard-index__content')
            .openUserActionMenu()
            .click('.sw-admin-menu__profile-item')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals('Dein Profil');

        browser
            .expect.element('input[name=sw-field--user-email]').to.have.value.that.equals('test@test.de');
    }
};
