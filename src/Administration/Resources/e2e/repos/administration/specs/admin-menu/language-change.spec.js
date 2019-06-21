module.exports = {
    '@tags': ['language-change', 'language', 'change', 'admin-menu'],
    'open admin menu': (browser) => {
        browser
            .waitForElementNotPresent('.sw-admin-menu__user-actions-toggle .sw-loader')
            .click('.sw-admin-menu__item--sw-dashboard .sw-admin-menu__navigation-link');
    },
    'toggle different admin menu appearances, change and assert administration language': (browser) => {
        browser
            .waitForElementVisible('.sw-dashboard-index__content')
            .waitForElementNotPresent('.sw-admin-menu__user-actions-toggle .sw-loader')
            .openUserActionMenu()
            .click('.sw-admin-menu__toggle')
            .waitForElementNotVisible('.sw-admin-menu__user-type')
            .click('.sw-admin-menu__toggle')
            .waitForElementVisible('.sw-admin-menu__user-type');
    }
};
