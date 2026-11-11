/**
 * @sw-package framework
 */

const fs = require('node:fs');

/**
 * Docker creates `/.dockerenv`, Podman creates `/run/.containerenv`.
 */
const MARKER_FILES = ['/.dockerenv', '/run/.containerenv'];

/**
 * Tells whether the watcher runs in a container, where no browser can be opened.
 *
 * @param {function(string): boolean} exists
 * @returns {boolean}
 */
function isContainer(exists = fs.existsSync) {
    return MARKER_FILES.some((file) => exists(file));
}

module.exports = isContainer;
