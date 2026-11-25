/**
 * @sw-package framework
 */

/**
 * The shop renders its own origin into the page in three encodings:
 * plain in HTML and CSS, JSON encoded in data attributes and responses,
 * and JavaScript escaped in the Symfony profiler toolbar.
 */
const ENCODINGS = [
    (origin) => origin,
    (origin) => origin.replaceAll('/', '\\/'),
    (origin) => origin.replaceAll(':', '\\u003A').replaceAll('/', '\\/'),
];

/**
 * The origin must end where the path or the string literal ends,
 * so that `http://localhost` does not match `http://localhost:9999`.
 */
const ORIGIN_BOUNDARY = /(?=\\?\/|['"])/.source;

/**
 * Media and thumbnails stay with the shop, they do not need the hot reload.
 */
const APP_PATHS = ['/media/', '/thumbnail/'];

function escapeRegExp(value) {
    return value.replace(/[.*+?^${}()|[\]\\/]/g, '\\$&');
}

/**
 * Creates a function that moves every URL of the shop origin to the hot proxy origin.
 *
 * @param {string} appOrigin - origin of the shop, e.g. `http://shop.test`
 * @param {string} proxyOrigin - origin of the hot proxy, e.g. `http://shop.test:9998`
 * @returns {function(string): string}
 */
module.exports = function createOriginRewriter(appOrigin, proxyOrigin) {
    const rules = ENCODINGS.map((encode) => ({
        pattern: new RegExp(escapeRegExp(encode(appOrigin)) + ORIGIN_BOUNDARY, 'g'),
        replacement: encode(proxyOrigin),
    }));
    const appPathRules = APP_PATHS.map((appPath) => ({
        pattern: new RegExp(escapeRegExp(`${proxyOrigin}${appPath}`), 'g'),
        replacement: `${appOrigin}${appPath}`,
    }));

    return (text) => [...rules, ...appPathRules].reduce(
        (result, rule) => result.replace(rule.pattern, rule.replacement),
        text,
    );
};
