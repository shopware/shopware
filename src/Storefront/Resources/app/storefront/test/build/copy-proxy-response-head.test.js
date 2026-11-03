const copyProxyResponseHead = require('../../build/copy-proxy-response-head');

/**
 * @sw-package framework
 */
describe('copy-proxy-response-head', () => {
    function createResponse() {
        return {
            statusCode: 200,
            headers: {},
            setHeader(name, value) {
                this.headers[name] = value;
            },
        };
    }

    const rewriteUrl = (url) => url.replace('http://shop.test/', 'http://shop.test:9998/');

    test('copies the status code and the content headers', () => {
        const res = createResponse();

        copyProxyResponseHead({
            statusCode: 500,
            headers: {
                'content-type': 'text/html; charset=UTF-8',
                'cache-control': 'no-cache, private',
            },
        }, res, rewriteUrl);

        expect(res.statusCode).toBe(500);
        expect(res.headers).toEqual({
            'content-type': 'text/html; charset=UTF-8',
            'cache-control': 'no-cache, private',
        });
    });

    test('rewrites the redirect location to the proxy origin', () => {
        const res = createResponse();

        copyProxyResponseHead({
            statusCode: 302,
            headers: { location: 'http://shop.test/account/login?redirectTo=frontend.account.home.page' },
        }, res, rewriteUrl);

        expect(res.statusCode).toBe(302);
        expect(res.headers.location).toBe('http://shop.test:9998/account/login?redirectTo=frontend.account.home.page');
    });

    test('skips cookies and headers that describe the original body', () => {
        const res = createResponse();

        copyProxyResponseHead({
            statusCode: 200,
            headers: {
                'set-cookie': ['session-=abc'],
                'content-length': '1234',
                'transfer-encoding': 'chunked',
                connection: 'keep-alive',
                'content-type': 'text/html',
            },
        }, res, rewriteUrl);

        expect(res.headers).toEqual({ 'content-type': 'text/html' });
    });
});
