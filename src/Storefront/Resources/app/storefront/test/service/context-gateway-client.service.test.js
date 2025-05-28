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
        const result = await contextGatewayClient.call({}, false);

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

    it('should handle window reload on autonavigate', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                status: 200,
                json: () => Promise.resolve({
                    token: '12345678',
                }),
            })
        );

        const contextGatewayClient = new ContextGatewayClient('test');
        await contextGatewayClient.call({}, true);

        expect(window.location.href).toBe('');
        expect(window.location.reload).toHaveBeenCalled();
    });

    it('should handle window redirect on autonavigate', async () => {
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
        await contextGatewayClient.call({}, true);

        expect(window.location.href).toBe('https://example.com/redirect');
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
            current: 'https://platform.dev.localhost/checkout/register',
            redirect: 'http://de-platform.dev.localhost/de',
            expected: 'http://de-platform.dev.localhost/de/checkout/register',
        },
        {
            current: 'https://platform.dev.localhost/checkout/register?foo=bar',
            redirect: 'http://de-platform.dev.localhost/de/',
            expected: 'http://de-platform.dev.localhost/de/checkout/register?foo=bar',
        },
        {
            current: 'https://platform.dev.localhost/checkout/register#top',
            redirect: 'http://de-platform.dev.localhost/de/',
            expected: 'http://de-platform.dev.localhost/de/checkout/register#top',
        },
        {
            current: 'https://platform.dev.localhost/',
            redirect: 'http://de-platform.dev.localhost/de/',
            expected: 'http://de-platform.dev.localhost/de/',
        },
    ])('redirects correctly for $redirect + $current', async ({ current, redirect, expected }) => {
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

        const contextGatewayClient = new ContextGatewayClient('test');
        await contextGatewayClient.call({}, true);

        expect(window.location.href).toBe(expected);
        expect(window.location.reload).not.toHaveBeenCalled();
    });
});
