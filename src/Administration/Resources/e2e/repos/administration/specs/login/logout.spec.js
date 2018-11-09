const loginPage = require('../../page-objects/sw-login.page-object.js');
module.exports = {
    '@tags': ['login', 'logout'],
    'view dashboard': (browser) => {
        browser
            .waitForElementVisible('.sw-dashboard-index__content');
    },
    'log out right away': (browser) => {
        const page = loginPage(browser);
        page.fastLogout('admin');
    },
    'verify logout': (browser) => {
        browser
            .waitForElementVisible('.sw-login__container')
            .waitForElementVisible('.sw-login__submit')
            .url('http://localhost:8000/admin#/sw/dashboard/index')
            .waitForElementVisible('.sw-login');
    },
    after: (browser) => {
        browser.end();
    }
};
