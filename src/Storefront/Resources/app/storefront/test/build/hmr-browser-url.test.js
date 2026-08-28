const getBrowserUrl = require('../../build/hmr-browser-url');

/**
 * @sw-package framework
 */
describe('hmr-browser-url', () => {
    test('uses the selected sales channel path with the proxy origin', () => {
        const browserUrl = getBrowserUrl(
            new URL('http://127.0.0.1:9998'),
            new URL('http://127.0.0.1:8022/de'),
        );

        expect(browserUrl).toBe('http://127.0.0.1:9998/de');
    });

    test('uses the proxy root when the selected sales channel has no path', () => {
        const browserUrl = getBrowserUrl(
            new URL('http://127.0.0.1:9998'),
            new URL('http://127.0.0.1:8022'),
        );

        expect(browserUrl).toBe('http://127.0.0.1:9998/');
    });
});
