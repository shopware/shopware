require('./../../common/helper/cliOutputHelper');
require('./../../common/helper/utils');

global.utils.renderWatcherUsage();

module.exports = {
    waitForConditionTimeout: 30000,
    asyncHookTimeout: 30000,

    beforeEach: (browser, done) => {
        browser.url(browser.launch_url);
        done();
    },
    afterEach(client, done) {
        global.logger.lineBreak();
        global.logger.title('Resetting database and cache to clean state...');

        const startTime = new Date();
        global.utils.clearDatabase()
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
