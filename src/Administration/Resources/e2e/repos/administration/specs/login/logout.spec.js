const loginPage = require('../../page-objects/module/sw-login.page-object.js');

module.exports = {
    '@tags': ['login', 'logout'],
    'view dashboard as correctly logged in user': (browser) => {
        const page = loginPage(browser);

        browser
            .waitForElementVisible('.sw-dashboard-index__content');

        page.verifyLogin('admin');
    },
    'log out right away': (browser) => {
        const page = loginPage(browser);

        browser.clickUserActionMenu('admin');
        page.logout('admin');
    },
    'verify logout': (browser) => {
        browser
            .waitForElementVisible('.sw-login__container')
            .waitForElementVisible('.sw-login__submit')
            .url(`${browser.launch_url}#/sw/dashboard/index`)
            .waitForElementVisible('.sw-login');
    },
    after: (browser) => {
        browser.end();
    }
};
