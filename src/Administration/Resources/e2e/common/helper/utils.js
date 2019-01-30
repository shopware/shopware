const util = require('util');
const exec = util.promisify(require('child_process').exec);

global.utils = {
    renderWatcherUsage: renderWatcherUsage,
    clearDatabase: clearDatabase
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