const createOriginRewriter = require('../../build/rewrite-origin-urls');

/**
 * @sw-package framework
 */
describe('rewrite-origin-urls', () => {
    const rewrite = createOriginRewriter('http://shop.test', 'http://shop.test:9998');

    test('rewrites plain URLs in HTML', () => {
        expect(rewrite('<a href="http://shop.test/navigation/1">'))
            .toBe('<a href="http://shop.test:9998/navigation/1">');
    });

    test('rewrites JSON encoded URLs', () => {
        expect(rewrite('{"redirectTo":"http:\\/\\/shop.test\\/detail\\/1"}'))
            .toBe('{"redirectTo":"http:\\/\\/shop.test:9998\\/detail\\/1"}');
    });

    test('rewrites the JavaScript escaped URLs of the Symfony profiler toolbar', () => {
        const toolbar = [
            '\'http\\u003A\\/\\/shop.test\\/_wdt\\/xxxxxx\'.replace(/xxxxxx/, newToken)',
            'url.indexOf(\'http\\u003A\\/\\/shop.test\')',
        ].join('\n');

        expect(rewrite(toolbar)).toBe([
            '\'http\\u003A\\/\\/shop.test\\u003A9998\\/_wdt\\/xxxxxx\'.replace(/xxxxxx/, newToken)',
            'url.indexOf(\'http\\u003A\\/\\/shop.test\\u003A9998\')',
        ].join('\n'));
    });

    test('leaves media and thumbnail URLs with the shop', () => {
        const html = '<img src="http://shop.test/media/a/b.png"><img src="http://shop.test/thumbnail/a/b.png">';

        expect(rewrite(html)).toBe(html);
    });

    test('leaves URLs of other origins alone', () => {
        const asset = '<script src="http://shop.test:9999/js/all.js"></script>';

        expect(rewrite(asset)).toBe(asset);
        expect(rewrite('https://shop.test/')).toBe('https://shop.test/');
    });
});
