// Register flags and fixtures globally so we have to import them one time
require('./../../common/helper/cliOutputHelper');
require('./flags.js');
require('./service/fixture.service');

const beforeScenarioActions = require('./specs/before-scenario.js');

const util = require('util');
const exec = util.promisify(require('child_process').exec);

module.exports = {

    waitForConditionTimeout: 30000,
    asyncHookTimeout: 60000,

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
    afterEach(client, done) {
        console.log();
        console.log("### Resetting database and cache to clean state...");

        const startTime = new Date();
        clearDatabase()
            .then(clearCache())
            .then(() => {
                const endTime = new Date() - startTime;
                global.logger.success(`Successfully reset database and cache! (${endTime / 1000}s)`);
                done();
            }).catch((err) => {
                global.logger.error(err);
                done(err);
            });
    }
};

/**
 * Clears the database using a child process on the shell of the system.
 *
 * @async
 * @returns {Promise<String|void>}
 */
function clearDatabase() {
    return exec(`${process.env.PROJECT_ROOT}psh.phar e2e:restore-db`);
}

/**
 * Clears the cache of the application using a child process in the shell of the system.
 *
 * @async
 * @returns {Promise<String|void>}
 */
function clearCache() {
    return exec(`${process.env.PROJECT_ROOT}bin/console cache:clear`);
}
