// Register flags and fixtures globally so we have to import them one time
require('./flags.js');
require('./service/fixture.service');

const beforeScenarioActions = require('./specs/before-scenario.js');

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
        browser.url(browser.launch_url);

        browser.execute(function () {
            // Disable the auto closing of notifications globally.
            Shopware.State.getStore('notification')._defaults.autoClose = false;

            // Return bearer token
            return localStorage.getItem('bearerAuth');
        }, [], (data) => {
            if (!data.value) {
                beforeScenarioActions.login(browser, 'admin', 'shopware', done);
            }
            beforeScenarioActions.hideToolbarIfVisible(browser);

            done();
        });
    },
    afterEach(done) {
        console.log("### Setting database to clean state...");

        if (process.env.E2E_ENV === "default") {
            exec(`mysql -u ${process.env.DB_USER} -p${process.env.DB_PASSWORD} -h 127.0.0.1 --port=4406 ${process.env.DB_NAME} < ${resolve('../temp/clean_db.sql')}`).then(() => {
                console.log('• ✓ - Done with reset');
                done();
            });
        } else {
            exec(`mysql -u ${process.env.DB_USER} -p${process.env.DB_PASSWORD} -h mysql --port=3306 ${process.env.DB_NAME} < ${resolve('../temp/clean_db.sql')}`).then(() => {
                console.log('• ✓ - Done with reset');
                done();
            });
        }
    }
};
