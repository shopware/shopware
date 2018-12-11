// Register flags globally so we have to import them one time
require('./flags.js');

const loginPage = require('./specs/before-scenario.js');

const util = require('util');
const path = require('path');
const exec = util.promisify(require('child_process').exec);

const resolve = (relativePath) => {
    return path.resolve(__dirname, '..', relativePath);
};

module.exports = {
    waitForConditionTimeout: 30000,
    asyncHookTimeout: 30000,

    beforeEach: (browser, done) => {
        // console.log(process.env);
        browser.url(browser.launch_url);

        browser.execute(function () {
            // Disable the auto closing of notifications globally.
            Shopware.State.getStore('notification')._defaults.autoClose = false;

            // Return bearer token
            return localStorage.getItem('bearerAuth');
        }, [], (data) => {
            if (!data.value) {
                loginPage.login(browser, 'admin', 'shopware', done);
            }

            done();
        });
    },
    afterEach(done) {
        console.log('Restoring clean state of database...');
        exec(`mysql -u ${process.env.DB_USER} -p${process.env.DB_PASSWORD} -h mysql ${process.env.DB_NAME} < ${resolve('../temp/clean_db.sql')}`).then(() => {
            done();
        });
    }
};
