const util = require('util');
const fs = require('fs');
const dircompare = require('dir-compare');
const exec = util.promisify(require('child_process').exec);

const rootPath = `${__dirname}/../../../../../../../../`;
const readdir = util.promisify(fs.readdir);

describe('webpack/bundle', () => {
    beforeEach(async () => {
        // Increase default timeout for the webpack build
        jest.setTimeout(5 * 60 * 1000);

        // if plugin folder exists
        if (fs.existsSync(`${rootPath}/custom/plugins`)) {
            // delete Backup Plugin Folder
            await exec(`rm -rf ${rootPath}/custom/plugins-backup`);
            // backup Plugin Folder
            await exec(`mv ${rootPath}/custom/plugins ${rootPath}/custom/plugins-backup`);
        }

        // if bundles folder exists
        if (fs.existsSync(`${rootPath}/public/bundles`)) {
            // delete Backup Bundle Folder
            await exec(`rm -rf ${rootPath}/public/bundles-backup`);
            // backup Bundle Folder
            await exec(`mv ${rootPath}/public/bundles ${rootPath}/public/bundles-backup`);
        }
    });

    afterEach(async () => {
        // delete Plugin Folder
        await exec(`rm -rf ${rootPath}/custom/plugins`);

        // delete Bundle Folder
        await exec(`rm -rf ${rootPath}/public/bundles`);

        // if plugins-backup folder exists
        if (fs.existsSync(`${rootPath}/custom/plugins-backup`)) {
            // restore Plugin Folder
            await exec(`mv ${rootPath}/custom/plugins-backup ${rootPath}/custom/plugins`);
        }

        // if bundles-backup folder exists
        if (fs.existsSync(`${rootPath}/public/bundles-backup`)) {
            // restore Bundle Folder
            await exec(`mv ${rootPath}/public/bundles-backup ${rootPath}/public/bundles`);
        }
    });

    it('should build the bundles with the administration folder', async (done) => {
        // build administration
        await exec(`cd ${rootPath} && ./psh.phar administration:build`);

        // get subfolders of bundles directory
        const directoryInfo = await readdir(`${rootPath}public/bundles/`);

        // delete bundles folder
        await exec(`rm -rf ${rootPath}/public/bundles`);

        // check if the administration folder exists
        if (directoryInfo.indexOf('administration') >= 0) {
            done();

            return;
        }

        done.fail('The directory does not contain the administration.');
    });

    it('the administration bundle should be the same independently of plugins', async (done) => {
        // create empty plugins folder
        await exec(`cd ${rootPath}/custom/ && mkdir plugins`);

        // refresh plugins
        await exec(`cd ${rootPath} && bin/console plugin:refresh`);

        // create clean bundle without plugin
        await exec(`cd ${rootPath} && ./psh.phar administration:build`);

        // rename bundle to "bundles_without_plugin"
        await exec(`cd ${rootPath} && mv ./public/bundles ./public/bundles_without_plugin`);

        // copy plugin to plugin folder
        await exec(`cp -R ${__dirname}/assets/ExamplePluginForTesting ${rootPath}/custom/plugins/`);

        // remove temp extension from plugin php file
        // eslint-disable-next-line max-len
        await exec(`cd ${rootPath} && mv ./custom/plugins/ExamplePluginForTesting/src/ExamplePluginForTesting.php.temp ./custom/plugins/ExamplePluginForTesting/src/ExamplePluginForTesting.php`);

        // refresh plugins
        await exec(`cd ${rootPath} && bin/console plugin:refresh`);

        // install and activate plugin
        await exec(`cd ${rootPath} && bin/console plugin:install --activate ExamplePluginForTesting`);

        // create bundle with plugin
        await exec(`cd ${rootPath} && ./psh.phar administration:build`);

        // eslint-disable-next-line max-len
        const folderComparison = await dircompare.compare(`${rootPath}/public/bundles/administration`, `${rootPath}/public/bundles_without_plugin/administration`);

        // uninstall and deactivate plugin
        await exec(`cd ${rootPath} && bin/console plugin:deactivate ExamplePluginForTesting`);
        await exec(`cd ${rootPath} && bin/console plugin:uninstall ExamplePluginForTesting`);

        // delete both bundles
        await exec(`rm -rf ${rootPath}/public/bundles`);
        await exec(`rm -rf ${rootPath}/public/bundles_without_plugin`);

        // delete plugins folder
        await exec(`cd ${rootPath}/custom/ && rm -rf plugins`);

        // create empty plugin folder
        await exec(`cd ${rootPath}/custom/ && mkdir plugins`);

        // refresh plugin list
        await exec(`cd ${rootPath} && bin/console plugin:refresh`);

        // expect no difference in diff
        if (folderComparison && folderComparison.same) {
            done();

            return;
        }

        // fail when there is a difference
        done.fail('The administration bundle is different when a plugin is installed.');
    });
});
