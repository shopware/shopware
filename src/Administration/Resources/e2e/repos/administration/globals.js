// Register flags and fixtures globally so we have to import them one time
require('./flags.js');
require('./service/fixture.service');

const beforeScenarioActions = require('./specs/before-scenario.js');

const util = require('util');
const exec = util.promisify(require('child_process').exec);

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
        console.log("### Resetting database to clean state...");
        exec(`${process.env.PROJECT_ROOT}psh.phar e2e:restore-db`).then(() => {
            console.log('• ✓ - Done with reset');
            done();
        }).catch((err) => {
            console.error(err);
        });
    }
};
