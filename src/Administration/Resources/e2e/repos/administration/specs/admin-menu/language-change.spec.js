module.exports = {
    '@tags': ['language-change', 'language', 'change', 'admin-menu'],
    'open admin menu': (browser) => {
        browser
            .waitForElementVisible('.sw-admin-menu__item--sw-dashboard .sw-admin-menu__navigation-link')
            .click('.sw-admin-menu__item--sw-dashboard .sw-admin-menu__navigation-link');
    },
    'toggle different admin menu appearances, change and assert administration language': (browser) => {
        browser
            .waitForElementVisible('.sw-dashboard-index__content')
            .clickUserActionMenu('admin')
            .click('.sw-admin-menu__toggle')
            .waitForElementNotVisible('.sw-admin-menu__user-type')
            .click('.sw-admin-menu__toggle')
            .waitForElementVisible('.sw-admin-menu__user-type');
    },
    'change and assert language': (browser) => {
        browser.expect.element('.sw-admin-menu__change-language-action').to.have.text.that.equals('Change language');

        browser
            .click('.sw-admin-menu__change-language-action')
            .expect.element('.sw-admin-menu__change-language-action').to.have.text.that.equals('Sprache wechseln');
        browser
            .refresh()
            .expect.element('.sw-search-bar__input').to.have.attribute('placeholder').that.equals('Finde Produkte, Kunden, Bestellungen');
    },
    after: (browser) => {
        browser.end();
    }
};
