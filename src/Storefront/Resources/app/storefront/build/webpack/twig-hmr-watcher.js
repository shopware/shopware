/* eslint no-console: 0 */

/**
 * @sw-package framework
 */
const chalk = require('chalk');
const chokidar = require('chokidar');
const { createTwigUpdateBatcher, describeTwigUpdate, DEBOUNCE_MS } = require('../twig-hmr/update-batcher');

/**
 * Watches the Twig files and sends the changed files to the browser as `sw-twig-update` message.
 * The client in `src/dev/twig-hmr/twig-hmr.client.js` renders the page again and morphs the DOM.
 *
 * @param {import('webpack-dev-server')} devServer
 * @param {string[]} watchPaths - glob patterns of the watched Twig files
 * @param {string} projectRootPath
 * @returns {import('chokidar').FSWatcher}
 */
module.exports = function setupTwigHmrWatcher(devServer, watchPaths, projectRootPath) {
    console.log(chalk.cyan(`[sw-twig-hmr] watching ${watchPaths.length} view directories`));

    const record = createTwigUpdateBatcher((update) => {
        console.log(chalk.cyan(describeTwigUpdate(update)));
        if (devServer.webSocketServer) {
            devServer.sendMessage(devServer.webSocketServer.clients, 'sw-twig-update', update);
        }
    });

    return chokidar.watch(watchPaths, {
        cwd: projectRootPath,
        ignoreInitial: true,
        ignorePermissionErrors: true,
        awaitWriteFinish: { stabilityThreshold: DEBOUNCE_MS, pollInterval: 10 },
    }).on('all', record);
};
