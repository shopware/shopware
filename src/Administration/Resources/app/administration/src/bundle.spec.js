/**
 * @package admin
 */

/* global adminPath */
/* global projectRoot */

const util = require('util');
const fs = require('fs');
const path = require('path');
const { sep } = require('path');
const dircompare = require('dir-compare');
const exec = util.promisify(require('child_process').exec);

const readdir = util.promisify(fs.readdir);

jest.spyOn(global.console, 'info').mockImplementation(() => jest.fn());

/**
 * This test is skipped. It should only be executed manually when
 * a developer want to make sure that the webpack build with and
 * without plugins are delivering the same code.
 */
// eslint-disable-next-line jest/no-disabled-tests
describe.skip('webpack/bundle', () => {
    beforeEach(async () => {
        // Increase default timeout for the webpack build
        jest.setTimeout(5 * 60 * 1000);

        // if plugin folder exists
        if (fs.existsSync(path.resolve(projectRoot, 'custom/plugins'))) {
            // delete Backup Plugin Folder
            await exec(`rm -rf ${path.resolve(projectRoot, 'custom/plugins-backup')}`);
            // backup Plugin Folder
            // eslint-disable-next-line max-len
            await exec(
                `mv ${path.resolve(projectRoot, 'custom/plugins')} ${path.resolve(projectRoot, 'custom/plugins-backup')}`,
            );
        }

        // if bundles folder exists
        if (fs.existsSync(path.resolve(projectRoot, 'public/bundles'))) {
            // delete Backup Bundle Folder
            await exec(`rm -rf ${path.resolve(projectRoot, 'public/bundles-backup')}`);
            // backup Bundle Folder
            // eslint-disable-next-line max-len
            await exec(
                `mv ${path.resolve(projectRoot, 'public/bundles')} ${path.resolve(projectRoot, 'public/bundles-backup')}`,
            );
        }
    });

    afterEach(async () => {
        // // delete Plugin Folder
        await exec(`rm -rf ${path.resolve(projectRoot, 'custom/plugins')}`);

        // delete Bundle Folder
        await exec(`rm -rf ${path.resolve(projectRoot, 'public/bundles')}`);

        // if plugins-backup folder exists
        if (fs.existsSync(`${path.resolve(projectRoot, 'custom/plugins-backup')}`)) {
            // restore Plugin Folder
            // eslint-disable-next-line max-len
            await exec(
                `mv ${path.resolve(projectRoot, 'custom/plugins-backup')} ${path.resolve(projectRoot, 'custom/plugins')}`,
            );
        }

        // if bundles-backup folder exists
        if (fs.existsSync(`${path.resolve(projectRoot, 'public/bundles-backup')}`)) {
            // restore Bundle Folder
            // eslint-disable-next-line max-len
            await exec(
                `mv ${path.resolve(projectRoot, 'public/bundles-backup')} ${path.resolve(projectRoot, 'public/bundles')}`,
            );
        }
    });

    it('should build the bundles with the administration folder', async () => {
        // build administration
        await exec(`cd ${projectRoot} && bin/console bundle:dump`);
        await exec(`cd ${adminPath} && npm run build`);
        await exec(`cd ${projectRoot} && bin/console assets:install`);

        // get subfolders of bundles directory
        const directoryInfo = await readdir(`${path.resolve(projectRoot, 'public/bundles')}${sep}`);

        // delete bundles folder
        await exec(`rm -rf ${path.resolve(projectRoot, 'public/bundles')}`);

        // check if the administration folder exists

        expect(directoryInfo).toContain('administration');
        // 'The directory does not contain the administration.'
    });

    it('the administration bundle should be the same independently of plugins', async () => {
        // create empty plugins folder
        await exec(`cd ${projectRoot} && mkdir -p custom/plugins`);

        // refresh plugins
        await exec(`cd ${projectRoot} && php bin${sep}console plugin:refresh`);

        // create clean bundle without plugin
        await exec(`cd ${projectRoot} && bin/console bundle:dump`);
        await exec(`cd ${adminPath} && npm run build`);
        await exec(`cd ${projectRoot} && bin/console assets:install`);

        // rename bundle to "bundles_without_plugin"
        // eslint-disable-next-line max-len
        await exec(
            `cd ${projectRoot} && mv ${path.resolve(projectRoot, 'public/bundles')} ${path.resolve(projectRoot, 'public/bundles_without_plugin')}`,
        );

        // copy plugin to plugin folder
        // eslint-disable-next-line max-len
        await exec(
            `cp -R ${path.resolve(__dirname, 'assets/ExamplePluginForTesting')} ${path.resolve(projectRoot, 'custom/plugins')}${sep}`,
        );

        // remove temp extension from plugin php file
        // eslint-disable-next-line max-len
        await exec(
            `cd ${projectRoot} && mv ${path.resolve(projectRoot, 'custom/plugins/ExamplePluginForTesting/src/ExamplePluginForTesting.php.temp')} ${path.resolve(projectRoot, 'custom/plugins/ExamplePluginForTesting/src/ExamplePluginForTesting.php')}`,
        );

        // refresh plugins
        await exec(`cd ${projectRoot} && php bin${sep}console plugin:refresh`);

        // install and activate plugin
        await exec(`cd ${projectRoot} && php bin${sep}console plugin:install --activate ExamplePluginForTesting`);

        // create bundle with plugin
        await exec(`cd ${projectRoot} && bin/console bundle:dump`);
        await exec(`cd ${adminPath} && npm run build`);
        await exec(`cd ${projectRoot} && bin/console assets:install`);

        // eslint-disable-next-line max-len
        const folderComparison = await dircompare.compare(
            `${path.resolve(projectRoot, 'public/bundles/administration')}`,
            `${path.resolve(projectRoot, 'public/bundles_without_plugin/administration')}`,
        );

        // uninstall and deactivate plugin
        await exec(`cd ${projectRoot} && php bin${sep}console plugin:deactivate ExamplePluginForTesting`);
        await exec(`cd ${projectRoot} && php bin${sep}console plugin:uninstall ExamplePluginForTesting`);

        // delete both bundles
        await exec(`rm -rf ${path.resolve(projectRoot, 'public/bundles')}`);
        await exec(`rm -rf ${path.resolve(projectRoot, 'public/bundles_without_plugin')}`);

        // delete plugins folder
        await exec(`cd ${projectRoot} && rm -rf ${path.resolve(projectRoot, 'custom/plugins')}`);

        // create empty plugin folder
        await exec(`cd ${projectRoot} && mkdir ${path.resolve(projectRoot, 'custom/plugins')}`);

        // refresh plugin list
        await exec(`cd ${projectRoot} && php bin${sep}console plugin:refresh`);

        // expect no difference in diff
        expect(folderComparison).toBe(folderComparison.same);
        // 'The administration bundle is different when a plugin is installed.'
    });
});
