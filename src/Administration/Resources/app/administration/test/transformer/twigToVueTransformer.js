/**
 * @sw-package framework
 *
 * Transforms Twig/HTML templates into a CommonJS module exporting the template string.
 * Strips HTML and Vue/Twig comments before export.
 *
 * WHY THIS FILE IS NEEDED:
 * This transformer is required for Jest to handle .twig and .html template files.
 * Shopware uses Twig.js (the 'twig' npm package) as its templating engine instead
 * of standard Vue Single File Components (SFCs). This architectural choice enables
 * the extensibility system: plugins can override or extend template blocks at runtime.
 *
 * Twig.js cannot be removed without:
 * - Migrating 970+ .html.twig files to .vue SFCs
 * - Replacing the entire block-based template extensibility mechanism
 * - Breaking the plugin ecosystem that relies on template overrides
 *
 * SECURITY NOTES:
 * - HTML comments: regex removes ALL <!-- sequences including unclosed ones
 * - Twig comments: regex removes ALL {# sequences including unclosed ones
 * - MD5 replaced with SHA-256 for cache key generation
 */
const crypto = require('crypto');

function getCacheKey(fileData, filePath, configStr) {
    return crypto.createHash('sha256')
        .update(fileData + filePath + configStr, 'utf8')
        .digest('hex');
}
exports.getCacheKey = getCacheKey;

function process(src) {
    // Remove HTML comments (including unclosed ones at end of string)
    src = src.replaceAll(/<!--[\s\S]*?(?:-->|$)/g, '');
    // Remove any remaining <!-- sequences (safety measure)
    src = src.replaceAll('<!--', '');
    // Remove Twig comments (including unclosed ones)
    src = src.replaceAll(/\{#[\s\S]*?(?:#\}|$)/g, '');
    // Remove any remaining {# sequences (safety measure)
    src = src.replaceAll('{#', '');

    return {
        code: '/* istanbul ignore file */\nmodule.exports = ' + JSON.stringify(src) + ';',
    };
}
exports.process = process;
