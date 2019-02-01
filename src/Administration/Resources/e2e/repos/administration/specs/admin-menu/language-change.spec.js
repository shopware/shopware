module.exports = {
    '@tags': ['language-change','language','change', 'admin-menu'],
    'open admin menu': (browser) => {
        browser
            .waitForElementVisible('.sw-admin-menu__navigation-link')
            .click('.sw-admin-menu__navigation-link')
            .waitForElementVisible('.sw-admin-menu__user-name')
            .assert.containsText('.sw-admin-menu__user-name', 'admin');
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
        browser
            .click('.sw-admin-menu__change-language-action')
            .assert.containsText('.sw-admin-menu__change-language-action', 'Sprache wechseln')
            .click('.sw-admin-menu__change-language-action')
            .assert.containsText('.sw-admin-menu__change-language-action', 'Change language');
    },
    after: (browser) => {
        browser.end();
    }
};
