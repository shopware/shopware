/**
 * @sw-package framework
 */

/**
 * Whether `filename` belongs to an installed dependency.
 * Shared by the transformers, so a JS/TS file and a `.vue` file of one package count as the same kind.
 *
 * @example
 * isDependencyFile('/admin/node_modules/vue/index.js'); // → true
 * isDependencyFile('/admin/src/app/main.ts'); // → false
 *
 * @param {string} filename
 * @returns {boolean}
 */
function isDependencyFile(filename) {
    return filename.replace(/\\/g, '/').includes('/node_modules/');
}

module.exports = { isDependencyFile };
