// Register flags and fixtures globally so we have to import them one time
require('./../../common/helper/cliOutputHelper');
require('./../../common/flags.js');
require('../../common/service/administration/fixture.service');
require('../../common/service/storefront/fixture.service');

const beforeScenarioActions = require('./specs/before-scenario.js');

const util = require('util');
const exec = util.promisify(require('child_process').exec);

renderWatcherUsage();

module.exports = {

    waitForConditionTimeout: 30000,
    asyncHookTimeout: 60000,

    beforeEach: (browser, done) => {
        let launch_url = browser.launch_url;

        if (process.env.APP_WATCH === 'true') {
            launch_url = launch_url.replace('8000', process.env.DEVPORT);
        }
        browser.url(launch_url);

        return global.AdminFixtureService.apiClient.loginToAdministration().then((result) => {
            const startTime = new Date();

            return browser.execute(function (result) {
                localStorage.setItem('bearerAuth', JSON.stringify(result));

                // Disable the auto closing of notifications globally.
                Shopware.State.getStore('notification')._defaults.autoClose = false;

                // Return bearer token
                return localStorage.getItem('bearerAuth');
            }, [result], (data) => {
                if (!data.value) {
                    beforeScenarioActions.login(browser, 'admin', 'shopware');
                }

                const endTime = new Date() - startTime;
                global.logger.success(`Logged in successfully! (${endTime / 1000}s)`);
                global.logger.lineBreak();
            });
        }).then(() => {
            if (!browser.checkIfElementExists('.sw-admin-menu__header-logo')) {
                browser.waitForElementVisible('.sw-admin-menu__header-logo');
            }
            beforeScenarioActions.hideToolbarIfVisible(browser);
            done();
        });
    },
    afterEach(client, done) {
        global.logger.lineBreak();
        global.logger.title('Resetting database and cache to clean state...');

        const startTime = new Date();
        clearDatabase()
            .then(global.AdminFixtureService.apiClient.clearCache())
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
 * Provide corresponding log entries if the tests will be launched on DEVPORT
 *
 */
function renderWatcherUsage() {
    if (process.env.APP_WATCH === 'true') {
        global.logger.lineBreak();
        global.logger.title('Usage of administration:watch');
        global.logger.success(`Launching on port ${process.env.DEVPORT}`);
    }
}

/**
 * Clears the database using a child process on the shell of the system.
 *
 * @async
 * @returns {Promise<String|void>}
 */
function clearDatabase() {
    return exec(`${process.env.PROJECT_ROOT}psh.phar e2e:restore-db`);
}
