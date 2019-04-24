// Register flags and fixtures globally so we have to import them one time
require('./../../common/helper/cliOutputHelper');
require('./../../common/flags.js');
require('../../common/service/administration/fixture.service');
require('../../common/service/saleschannel/fixture.service');

const util = require('util');
const exec = util.promisify(require('child_process').exec);
const beforeScenarioActions = require('./specs/before-scenario.js');

renderWatcherUsage();

module.exports = {

    waitForConditionTimeout: 30000,
    asyncHookTimeout: 60000,

    beforeEach: (browser, done) => {
        let launchUrl = browser.launch_url;

        if (process.env.APP_WATCH === 'true') {
            launchUrl = launchUrl.replace('8000', process.env.DEVPORT);
        }
        browser.url(launchUrl);
        const startTime = new Date();

        return global.AdminFixtureService.apiClient.loginToAdministration().then((result) => {
            return browser.execute(function onExecuteOnBrowser(loginResult) {
                localStorage.setItem('bearerAuth', JSON.stringify(loginResult));

                // Disable the auto closing of notifications globally.
                Shopware.State.getStore('notification').state.defaults.autoClose = false;

                // Return bearer token
                return localStorage.getItem('bearerAuth');
            }, [result], (data) => {
                if (!data.value) {
                    beforeScenarioActions.loginIfSessionFailed(browser, 'admin', 'shopware');
                }
            });
        }).then(() => {
            beforeScenarioActions.loginIfSessionFailed(browser, 'admin', 'shopware');

            if (!browser.checkIfElementExists('.sw-admin-menu__header-logo')) {
                browser.waitForElementVisible('.sw-admin-menu__header-logo');
            }

            const endTime = new Date() - startTime;
            global.logger.success(`Logged in successfully! (${endTime / 1000}s)`);
            global.logger.lineBreak();
        }).then(() => {
            beforeScenarioActions.hideToolbarIfVisible(browser);
            done();
        });
    },
    afterEach(browser, done) {
        let startTime;
        getBrowserLog(browser)
            .then(() => {
                return new Promise((resolve) => {
                    browser.end(() => {
                        resolve();
                    });
                });
            })
            .then(() => {
                global.logger.lineBreak();
                global.logger.title('Resetting database and cache to clean state...');
            })
            .then(() => {
                startTime = new Date();
                return clearDatabase();
            })
            .then(global.AdminFixtureService.apiClient.clearCache())
            .then(() => {
                const endTime = new Date() - startTime;
                global.logger.success(`Successfully reset database and cache! (${endTime / 1000}s)`);
                done();
            })
            .catch((err) => {
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
    global.logger.success(`Launching on DB ${process.env.DB_NAME}`);
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

/**
 * Gets the last log entries from the browser. Please keep in mind that the current session
 * needs to be open to grab the logs of the browser.
 *
 * @params {NightwatchClient} browser
 * @returns {Promise<T>}
 */
function getBrowserLog(browser) {
    return new Promise((resolve) => {
        browser.isLogAvailable('browser', (isAvailable) => {
            if (!isAvailable) {
                resolve();
                return;
            }

            global.logger.title('Browser log from last test suite');
            browser.getLog('browser', (logEntries) => {
                logEntries.forEach((log) => {
                    console.log(`[${log.level}.${log.source}]: ${log.message}`);
                });
            });
            resolve();
        });
    });
}
