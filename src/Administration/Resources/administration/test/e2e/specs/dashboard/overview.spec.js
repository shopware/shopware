const loginPage = require('../login.js');

module.exports = {
    before: loginPage.login,

    'view dashboard': (browser) => {
        browser
            .waitForElementVisible('.sw-dashboard-index__content')
            .end();
    }
};
