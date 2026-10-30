/* eslint no-console: 0 */

/**
 * @sw-package framework
 */
const chalk = require('chalk');
const chokidar = require('chokidar');

const DEBOUNCE_MS = 50;

/**
 * Watches the Twig files and sends the changed files to the browser as `sw-twig-update` message.
 * The client in `src/dev/twig-hmr/twig-hmr.client.js` renders the page again and morphs the DOM.
 *
 * Saves within the debounce window are sent as one message, so an editor that writes several files only triggers one refresh.
 * `structural` is true if a file was added or removed. A compiled template keeps the parent that it extended when it was compiled,
 * so the server then drops its compiled templates.
 *
 * @param {import('webpack-dev-server')} devServer
 * @param {string[]} watchPaths - glob patterns of the watched Twig files
 * @param {string} projectRootPath
 * @returns {import('chokidar').FSWatcher}
 */
module.exports = function setupTwigHmrWatcher(devServer, watchPaths, projectRootPath) {
    let changedFiles = new Set();
    let structural = false;
    let timer = null;

    console.log(chalk.cyan(`[sw-twig-hmr] watching ${watchPaths.length} view directories`));

    return chokidar.watch(watchPaths, {
        cwd: projectRootPath,
        ignoreInitial: true,
        ignorePermissionErrors: true,
        awaitWriteFinish: { stabilityThreshold: DEBOUNCE_MS, pollInterval: 10 },
    }).on('all', (event, file) => {
        if (!['add', 'change', 'unlink'].includes(event)) {
            return;
        }

        changedFiles.add(file);
        structural = structural || event !== 'change';
        clearTimeout(timer);
        timer = setTimeout(() => {
            const message = { files: [...changedFiles], structural };
            changedFiles = new Set();
            structural = false;

            console.log(chalk.cyan(`[sw-twig-hmr] ${message.files.join(', ')}${message.structural ? ' (added or removed)' : ''}`));
            if (devServer.webSocketServer) {
                devServer.sendMessage(devServer.webSocketServer.clients, 'sw-twig-update', message);
            }
        }, DEBOUNCE_MS);
    });
};
