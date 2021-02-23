// eslint-disable-next-line
const util = require('util');
const fs = require('fs');
// eslint-disable-next-line
const path = require('path');
// eslint-disable-next-line
const { sep } = require('path');
const dircompare = require('dir-compare');
const exec = util.promisify(require('child_process').exec);

const rootPath = `${path.resolve(__dirname, '../../../../../../../..')}${sep}`;
const readdir = util.promisify(fs.readdir);

jest.spyOn(global.console, 'info').mockImplementation(() => jest.fn());

const runBundleTests = process.env.CI === 'true' ? describe : describe.skip;

runBundleTests('webpack/bundle', () => {
    beforeEach(async () => {
        // Increase default timeout for the webpack build
        jest.setTimeout(5 * 60 * 1000);

        // if plugin folder exists
        if (fs.existsSync(path.resolve(rootPath, 'custom/plugins'))) {
            // delete Backup Plugin Folder
            await exec(`rm -rf ${path.resolve(rootPath, 'custom/plugins-backup')}`);
            // backup Plugin Folder
            // eslint-disable-next-line max-len
            await exec(`mv ${path.resolve(rootPath, 'custom/plugins')} ${path.resolve(rootPath, 'custom/plugins-backup')}`);
        }

        // if bundles folder exists
        if (fs.existsSync(path.resolve(rootPath, 'public/bundles'))) {
            // delete Backup Bundle Folder
            await exec(`rm -rf ${path.resolve(rootPath, 'public/bundles-backup')}`);
            // backup Bundle Folder
            // eslint-disable-next-line max-len
            await exec(`mv ${path.resolve(rootPath, 'public/bundles')} ${path.resolve(rootPath, 'public/bundles-backup')}`);
        }
    });

    afterEach(async () => {
        // delete Plugin Folder
        await exec(`rm -rf ${path.resolve(rootPath, 'custom/plugins')}`);

        // delete Bundle Folder
        await exec(`rm -rf ${path.resolve(rootPath, 'public/bundles')}`);

        // if plugins-backup folder exists
        if (fs.existsSync(`${path.resolve(rootPath, 'custom/plugins-backup')}`)) {
            // restore Plugin Folder
            // eslint-disable-next-line max-len
            await exec(`mv ${path.resolve(rootPath, 'custom/plugins-backup')} ${path.resolve(rootPath, 'custom/plugins')}`);
        }

        // if bundles-backup folder exists
        if (fs.existsSync(`${path.resolve(rootPath, 'public/bundles-backup')}`)) {
            // restore Bundle Folder
            // eslint-disable-next-line max-len
            await exec(`mv ${path.resolve(rootPath, 'public/bundles-backup')} ${path.resolve(rootPath, 'public/bundles')}`);
        }
    });

    it('should build the bundles with the administration folder', async (done) => {
        // build administration
        await exec(`cd ${rootPath} && php psh.phar administration:build`);

        // get subfolders of bundles directory
        const directoryInfo = await readdir(`${path.resolve(rootPath, 'public/bundles')}${sep}`);

        // delete bundles folder
        await exec(`rm -rf ${path.resolve(rootPath, 'public/bundles')}`);

        // check if the administration folder exists
        if (directoryInfo.indexOf('administration') >= 0) {
            done();

            return;
        }

        done.fail('The directory does not contain the administration.');
    });

    it('the administration bundle should be the same independently of plugins', async (done) => {
        // create empty plugins folder
        await exec(`cd ${path.resolve(rootPath, 'custom')} && mkdir plugins`);

        // refresh plugins
        await exec(`cd ${rootPath} && php bin${sep}console plugin:refresh`);

        // create clean bundle without plugin
        await exec(`cd ${rootPath} && php psh.phar administration:build`);

        // rename bundle to "bundles_without_plugin"
        // eslint-disable-next-line max-len
        await exec(`cd ${rootPath} && mv ${path.resolve(rootPath, 'public/bundles')} ${path.resolve(rootPath, 'public/bundles_without_plugin')}`);

        // copy plugin to plugin folder
        // eslint-disable-next-line max-len
        await exec(`cp -R ${path.resolve(__dirname, 'assets/ExamplePluginForTesting')} ${path.resolve(rootPath, 'custom/plugins')}${sep}`);

        // remove temp extension from plugin php file
        // eslint-disable-next-line max-len
        await exec(`cd ${rootPath} && mv ${path.resolve(rootPath, 'custom/plugins/ExamplePluginForTesting/src/ExamplePluginForTesting.php.temp')} ${path.resolve(rootPath, 'custom/plugins/ExamplePluginForTesting/src/ExamplePluginForTesting.php')}`);

        // refresh plugins
        await exec(`cd ${rootPath} && php bin${sep}console plugin:refresh`);

        // install and activate plugin
        await exec(`cd ${rootPath} && php bin${sep}console plugin:install --activate ExamplePluginForTesting`);

        // create bundle with plugin
        await exec(`cd ${rootPath} && php psh.phar administration:build`);

        // eslint-disable-next-line max-len
        const folderComparison = await dircompare.compare(`${path.resolve(rootPath, 'public/bundles/administration')}`, `${path.resolve(rootPath, 'public/bundles_without_plugin/administration')}`);

        // uninstall and deactivate plugin
        await exec(`cd ${rootPath} && php bin${sep}console plugin:deactivate ExamplePluginForTesting`);
        await exec(`cd ${rootPath} && php bin${sep}console plugin:uninstall ExamplePluginForTesting`);

        // delete both bundles
        await exec(`rm -rf ${path.resolve(rootPath, 'public/bundles')}`);
        await exec(`rm -rf ${path.resolve(rootPath, 'public/bundles_without_plugin')}`);

        // delete plugins folder
        await exec(`cd ${rootPath} && rm -rf ${path.resolve(rootPath, 'custom/plugins')}`);

        // create empty plugin folder
        await exec(`cd ${rootPath} && mkdir ${path.resolve(rootPath, 'custom/plugins')}`);

        // refresh plugin list
        await exec(`cd ${rootPath} && php bin${sep}console plugin:refresh`);

        // expect no difference in diff
        if (folderComparison && folderComparison.same) {
            done();

            return;
        }

        // fail when there is a difference
        done.fail('The administration bundle is different when a plugin is installed.');
    });
});
