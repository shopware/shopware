import { GatewayClient } from './gateway-client';

describe('src/core/telemetry/product-analytics/gateway-client', () => {
    let client;
    let fetchMock;

    beforeEach(() => {
        jest.useFakeTimers();
        jest.setSystemTime(1710000000000);

        fetchMock = jest.fn().mockResolvedValue({
            ok: true,
            status: 200,
        });
        global.fetch = fetchMock;
        jest.spyOn(globalThis.crypto, 'randomUUID').mockReturnValueOnce('device-id-1').mockReturnValue('insert-id-1');

        window.localStorage.clear();
        window.sessionStorage.clear();

        Shopware.Store.get('context').app.config.shopId = 'shop-1';
        Shopware.Store.get('context').app.config.version = '6.8.0.0';
        Shopware.Store.get('context').app.config.appUrl = 'https://admin.shopware.test';
        Shopware.Context.app.systemCurrencyISOCode = 'EUR';
        Shopware.Application.view.router = {
            currentRoute: {
                value: {
                    name: 'sw.dashboard.index',
                    path: '/sw/dashboard/index',
                    fullPath: '/sw/dashboard/index?limit=25',
                },
            },
        };

        Object.defineProperty(window, 'screen', {
            configurable: true,
            value: {
                width: 1440,
                height: 900,
                orientation: {
                    type: 'landscape-primary',
                },
            },
        });

        client = new GatewayClient('https://gw.test', 'English');
        client.setOptOut(false);
    });

    afterEach(() => {
        jest.useRealTimers();
        jest.resetAllMocks();

        try {
            delete global.fetch;
        } catch {
            // ignore
        }
    });

    it('sends consent metric to the anonymous event endpoint with the provider-neutral payload', async () => {
        const time = Date.now();
        const properties = { foo: 'bar' };

        await client.trackConsentMetric('consent_given', properties, time);

        expect(fetchMock).toHaveBeenCalledTimes(1);

        const [
            url,
            options,
        ] = fetchMock.mock.calls[0];
        expect(url).toBe('https://gw.test/v2/event/anonymous');
        expect(options).toEqual(
            expect.objectContaining({
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'omit',
                keepalive: true,
            }),
        );
        expect(JSON.parse(options.body)).toEqual({
            context: {
                sw_version: '6.8.0.0',
            },
            events: [
                {
                    name: 'consent_given',
                    timestamp: time,
                    properties,
                },
            ],
        });
    });

    it('sends deleteUser request with shop and user ids', async () => {
        await client.deleteUser('shop-1', 'user-1');

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock).toHaveBeenCalledWith(
            'https://gw.test/v1/delete-user',
            expect.objectContaining({
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'omit',
                keepalive: true,
                body: JSON.stringify({ shop_id: 'shop-1', user_id: 'user-1' }),
            }),
        );
    });

    it('buffers tracked events and sends a batched Shopware-owned payload', async () => {
        client.init();
        client.identify('user-1');

        client.track('login', { foo: 'bar' });

        expect(fetchMock).not.toHaveBeenCalled();

        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);

        const [
            url,
            options,
        ] = fetchMock.mock.calls[0];
        expect(url).toBe('https://gw.test/v2/event');

        const payload = JSON.parse(options.body);
        expect(payload.user).toEqual({ shop_id: 'shop-1', id: 'user-1' });
        expect(payload.context).toEqual({
            sw_version: '6.8.0.0',
            sw_app_url: 'https://admin.shopware.test',
            sw_browser_url: window.location.origin,
            sw_user_agent: window.navigator.userAgent,
            sw_default_language: 'English',
            sw_default_currency: 'EUR',
            sw_screen_width: 1440,
            sw_screen_height: 900,
            sw_screen_orientation: 'landscape',
        });
        expect(payload.events).toHaveLength(1);
        expect(payload.events[0]).toEqual({
            name: 'login',
            timestamp: 1710000000000,
            session_id: 1710000000000,
            insert_id: 'insert-id-1',
            device_id: 'device-id-1',
            properties: {
                foo: 'bar',
                sw_page_name: 'sw.dashboard.index',
                sw_page_path: '/sw/dashboard/index',
                sw_page_full_path: '/sw/dashboard/index?limit=25',
            },
        });
    });

    it('retries failed tracked event batches after a backoff delay', async () => {
        fetchMock
            .mockResolvedValueOnce({
                ok: false,
                status: 503,
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
            });

        client.init();
        client.identify('user-1');
        client.track('page_viewed');

        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);

        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('does not schedule a retry when flushing without retry support', async () => {
        fetchMock.mockResolvedValueOnce({
            ok: false,
            status: 503,
        });

        client.init();
        client.identify('user-1');
        client.track('page_viewed');

        await client.flushWithoutRetry();

        expect(fetchMock).toHaveBeenCalledTimes(1);

        jest.advanceTimersByTime(30000);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('stops retrying after the configured retry limit is reached', async () => {
        fetchMock.mockResolvedValue({
            ok: false,
            status: 503,
        });

        client.init();
        client.identify('user-1');
        client.track('page_viewed');

        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);

        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);

        jest.advanceTimersByTime(2000);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(3);

        jest.advanceTimersByTime(30000);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(3);
    });

    it('clears persisted identifiers when storage is cleared', () => {
        client.init();
        client.identify('user-1');
        client.track('page_viewed');

        expect(window.localStorage.getItem('sw-product-analytics-device-id')).toBe('device-id-1');
        expect(window.sessionStorage.getItem('sw-product-analytics-session-id')).toBe('1710000000000');

        client.clearStorage();

        expect(window.localStorage.getItem('sw-product-analytics-device-id')).toBeNull();
        expect(window.sessionStorage.getItem('sw-product-analytics-session-id')).toBeNull();
        expect(client.getUserId()).toBeNull();
    });
});
