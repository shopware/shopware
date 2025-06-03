import ContextGatewayClient from '../../src/service/context-gateway-client.service';

/**
 * @package framework
 */
describe('Context gateway client service', () => {
    beforeEach(() => {
        delete window.location;

        window.location = { href: '', reload: jest.fn() };
        window['router']['frontend.gateway.context'] = 'https://example.com/gateway/context';
    });

    afterEach(() => {
        jest.resetAllMocks();
    });

    it('should handle token response', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                status: 200,
                json: () => Promise.resolve({
                    token: '12345678',
                    redirectUrl: 'https://example.com/redirect',
                }),
            })
        );

        const contextGatewayClient = new ContextGatewayClient('test');
        const result = await contextGatewayClient.call();

        expect(result).toEqual({
            token: '12345678',
            redirectUrl: 'https://example.com/redirect',
        });

        expect(global.fetch).toHaveBeenCalledWith('https://example.com/gateway/context', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ appName: 'test' }),
        });

        expect(window.location.href).toBe('');
        expect(window.location.reload).not.toHaveBeenCalled();
    });

    it('should handle bad requests', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: false,
                status: 400,
                statusText: 'Bad Request',
                text: () => Promise.resolve('Error'),
            })
        );

        const contextGatewayClient = new ContextGatewayClient('test');
        await expect(contextGatewayClient.call()).rejects.toThrow('Context gateway request failed for app \'test\': 400 Bad Request - Error');
    });


    test.each([
        {
            name: 'merges paths when no custom target',
            current: 'https://platform.dev.localhost/checkout/register?foo=bar#baz',
            redirect: 'http://de-platform.dev.localhost/de',
            customTarget: null,
            expectedHref: 'http://de-platform.dev.localhost/de/checkout/register?foo=bar',
            shouldReload: false,
        },
        {
            name: 'merges search params with custom target params',
            current: 'https://platform.dev.localhost/checkout/register?foo=bar&baz=bat',
            redirect: 'http://de-platform.dev.localhost/de',
            customTarget: 'custom/target?abc=def&ghi=jkl',
            expectedHref: 'http://de-platform.dev.localhost/de/custom/target?abc=def&ghi=jkl&foo=bar&baz=bat',
            shouldReload: false,
        },
        {
            name: 'merges search params with custom target params and does override',
            current: 'https://platform.dev.localhost/checkout/register?foo=bar',
            redirect: 'http://de-platform.dev.localhost/de',
            customTarget: 'custom/target?foo=bat',
            expectedHref: 'http://de-platform.dev.localhost/de/custom/target?foo=bat',
            shouldReload: false,
        },
        {
            name: 'uses custom target absolute path',
            current: 'https://platform.dev.localhost/checkout/register',
            redirect: 'http://de-platform.dev.localhost/de',
            customTarget: '/custom/target',
            expectedHref: 'http://de-platform.dev.localhost/custom/target',
            shouldReload: false,
        },
        {
            name: 'uses custom target relative path',
            current: 'https://platform.dev.localhost/checkout/register',
            redirect: 'http://de-platform.dev.localhost/de',
            customTarget: 'custom/target',
            expectedHref: 'http://de-platform.dev.localhost/de/custom/target',
            shouldReload: false,
        },
        {
            name: 'merges with root path correctly',
            current: 'https://platform.dev.localhost',
            redirect: 'http://de-platform.dev.localhost/de',
            customTarget: null,
            expectedHref: 'http://de-platform.dev.localhost/de',
            shouldReload: false,
        },
        {
            name: 'reloads if redirectUrl is undefined',
            current: 'https://platform.dev.localhost/checkout/register',
            redirect: undefined,
            customTarget: null,
            expectedHref: 'https://platform.dev.localhost/checkout/register',
            shouldReload: true,
        },
        {
            name: 'reloads if redirectUrl is null',
            current: 'https://platform.dev.localhost/checkout/register',
            redirect: null,
            customTarget: null,
            expectedHref: 'https://platform.dev.localhost/checkout/register',
            shouldReload: true,
        },
        {
            name: 'handles custom target as empty string',
            current: 'https://platform.dev.localhost/checkout/register',
            redirect: 'http://de-platform.dev.localhost/de',
            customTarget: '',
            expectedHref: 'http://de-platform.dev.localhost/de',
            shouldReload: false,
        },
        {
            name: 'no double slash on root merge',
            current: 'https://platform.dev.localhost/',
            redirect: 'http://de-platform.dev.localhost/de/',
            customTarget: null,
            expectedHref: 'http://de-platform.dev.localhost/de',
            shouldReload: false,
        },
        {
            name: 'handles custom target as single slash',
            current: 'https://platform.dev.localhost/checkout/register',
            redirect: 'http://de-platform.dev.localhost/de',
            customTarget: '/',
            expectedHref: 'http://de-platform.dev.localhost',
            shouldReload: false,
        },
        {
            name: 'custom target as full URL is treated as absolute override',
            current: 'https://platform.dev.localhost',
            redirect: 'http://de-platform.dev.localhost/de',
            customTarget: 'http://evil.com/phish',
            expectedHref: 'http://evil.com/phish',
            shouldReload: false,
        },
        {
            name: 'custom target with ./relative path',
            current: 'https://platform.dev.localhost/shop/checkout',
            redirect: 'http://de-platform.dev.localhost/de/',
            customTarget: './next',
            expectedHref: 'http://de-platform.dev.localhost/de/next',
            shouldReload: false,
        },
        {
            name: 'custom target with ../ path escapes merged base',
            current: 'https://platform.dev.localhost/shop/checkout',
            redirect: 'http://de-platform.dev.localhost/de/',
            customTarget: '../escaped',
            expectedHref: 'http://de-platform.dev.localhost/escaped',
            shouldReload: false,
        },
        {
            name: 'empty redirect and empty customTarget fallback',
            current: 'https://platform.dev.localhost/foo',
            redirect: '',
            customTarget: '',
            expectedHref: 'https://platform.dev.localhost/foo',
            shouldReload: true,
        },
        {
            name: 'redirect from root path with no customTarget',
            current: 'https://platform.dev.localhost/',
            redirect: 'http://de-platform.dev.localhost',
            customTarget: null,
            expectedHref: 'http://de-platform.dev.localhost',
            shouldReload: false,
        },
    ])('$name', async ({ current, redirect, customTarget, expectedHref, shouldReload }) => {
        window.location.href = current;

        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                status: 200,
                json: () => Promise.resolve({
                    token: '12345678',
                    redirectUrl: redirect,
                }),
            })
        );

        const client = new ContextGatewayClient('test');
        const tokenResponse = await client.call();
        client.navigate(tokenResponse, customTarget);

        expect(window.location.href).toBe(expectedHref);

        shouldReload
            ? expect(window.location.reload).toHaveBeenCalled()
            : expect(window.location.reload).not.toHaveBeenCalled();
    });
});
