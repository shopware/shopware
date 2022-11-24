/**
 * @package storefront
 */

const fs = require('fs-extra');
const path = require('path');

/**
 * Start time of the process for execution measuring
 * @type {[number, number]}
 */
const startTime = process.hrtime();

/**
 * Output directory
 * @type {String}
 */
const toDirectory = 'vendor';

/**
 * Directories to copy
 * @type {String[]}
 */
const fromDirectories = [
    'node_modules/bootstrap',
    'node_modules/tiny-slider',
    'node_modules/flatpickr',
];

/**
 * Iterates over the from directories and starts the copy process.
 *
 * @param {String[]}directories
 * @return {Promise[]}
 */
function iterateFromDirectories(directories) {
    return directories.map((relativeDirectory) => {
        const fullFromPath = path.join(__dirname, relativeDirectory);
        const folderName = relativeDirectory.split('/').pop();
        const fullToPath = path.join(__dirname, toDirectory, folderName);
        return fs.copy(fullFromPath, fullToPath)
            .then(() => {
                console.log(`- copied "${fullFromPath}" to "${fullToPath}"`);
            });
    });
}

function onCopyProcess(results) {
    // React to the result of the copy process
    Promise.all(results)
        .then(() => {
            console.log('');
            console.log('✓ Done, all directories / files copied successfully.');

            const endTime = process.hrtime(startTime);
            console.log(`Execution time: ${endTime[1] / 1000000}ms`);
        })
        .catch((err) => {
            console.log('');
            console.error('✖ An error occurred while copying files');

            const endTime = process.hrtime(startTime);
            console.log(`Execution time: ${endTime[1] / 1000000}ms`);

            throw err;
        })
}

onCopyProcess(iterateFromDirectories(fromDirectories));
