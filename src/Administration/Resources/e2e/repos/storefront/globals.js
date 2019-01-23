require('./../../common/helper/cliOutputHelper');

const util = require('util');
const exec = util.promisify(require('child_process').exec);

renderWatcherUsage();

module.exports = {
    waitForConditionTimeout: 30000,
    asyncHookTimeout: 30000,

    beforeEach: (browser, done) => {
        browser.url(browser.launch_url);
        done();
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
 * Provide corrensponding log entries if the tests will be launched on DEVPORT
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

/**
 * Clears the cache of the application using a child process in the shell of the system.
 *
 * @async
 * @returns {Promise<String|void>}
 */
function clearCache() {
    return exec(`rm -rf ${process.env.PROJECT_ROOT}/var/cache`);
}
