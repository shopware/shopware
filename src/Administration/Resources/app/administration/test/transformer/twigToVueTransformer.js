/**
 * @sw-package framework
 *
 * Transforms Twig/HTML templates into a CommonJS module exporting the template string.
 * Strips HTML and Vue/Twig comments before export.
 */
const crypto = require('crypto');

function getCacheKey(fileData, filePath, configStr) {
    return crypto.createHash('md5')
        .update(fileData + filePath + configStr, 'utf8')
        .digest('hex');
}
exports.getCacheKey = getCacheKey;

function process(src) {
    src = src.replaceAll(/<!--[\s\S]*?-->/gm, '');
    src = src.replaceAll(/^(?!\{#-)\{#[\s\S]*?#\}/gm, '');

    return {
        code: '/* istanbul ignore file */\nmodule.exports = ' + JSON.stringify(src) + ';',
    };
}
exports.process = process;
