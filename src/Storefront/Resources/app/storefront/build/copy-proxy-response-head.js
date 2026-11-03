/**
 * @sw-package framework
 */

/**
 * `set-cookie` is forwarded separately by the proxy.
 * The others describe the original body, which the proxy may rewrite.
 */
const SKIPPED_HEADERS = ['set-cookie', 'content-length', 'transfer-encoding', 'connection'];

/**
 * Copies the status code and headers of the shop response to the response of the hot proxy.
 * With `selfHandleResponse`, http-proxy-middleware leaves both to the caller.
 *
 * @param {import('node:http').IncomingMessage} proxyRes - response of the shop
 * @param {import('node:http').ServerResponse} res - response of the hot proxy
 * @param {function(string): string} rewriteUrl - maps a shop URL to the proxy URL
 */
module.exports = function copyProxyResponseHead(proxyRes, res, rewriteUrl) {
    res.statusCode = proxyRes.statusCode;

    Object.entries(proxyRes.headers).forEach(([name, value]) => {
        if (SKIPPED_HEADERS.includes(name)) {
            return;
        }

        res.setHeader(name, name === 'location' ? rewriteUrl(value) : value);
    });
};
