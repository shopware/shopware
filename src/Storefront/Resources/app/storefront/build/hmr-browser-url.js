/**
 * @sw-package framework
 * @deprecated tag:v6.8.0 - The HMR mode will be removed.
 */

/**
 * @param {URL} proxyUrl
 * @param {URL} domainUrl
 * @returns {string}
 */
function getBrowserUrl(proxyUrl, domainUrl) {
    const browserUrl = new URL(proxyUrl.origin);
    browserUrl.pathname = domainUrl.pathname;

    return browserUrl.toString();
}

module.exports = getBrowserUrl;
