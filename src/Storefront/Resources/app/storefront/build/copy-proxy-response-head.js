/**
 * @sw-package framework
 */

/**
 * `set-cookie` is forwarded separately by the proxy.
 * `content-length`, `transfer-encoding` and `connection` describe the original body, which the proxy may rewrite.
 * The Content Security Policy headers name the shop origin, but the proxy serves the page on its own port
 * and loads the Webpack assets from the live reload server, so the browser would block them.
 */
const SKIPPED_HEADERS = [
    'set-cookie',
    'content-length',
    'transfer-encoding',
    'connection',
    'content-security-policy',
    'content-security-policy-report-only',
];

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
