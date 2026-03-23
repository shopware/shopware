import { registerAmplitudeLogoutListener } from './amplitude-logout-listener';

describe('src/core/telemetry/product-analytics/amplitude-logout-listener', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        Shopware.Store.get('context').app.config.version = '6.7.0.0';
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    it('registers the logout listener and resets amplitude after flushing', () => {
        const flush = jest.fn();
        const reset = jest.fn();
        const setTransport = jest.fn();
        const addOnLogoutListener = jest.fn();

        Shopware.Service = jest.fn(() => ({
            addOnLogoutListener,
        }));

        registerAmplitudeLogoutListener(
            {
                flush,
                reset,
                setTransport,
            },
            'https://gateway.example',
        );

        jest.useFakeTimers();
        addOnLogoutListener.mock.calls[0][0]();

        expect(setTransport).toHaveBeenCalledWith('beacon');

        jest.runAllTimers();

        expect(flush).toHaveBeenCalled();
        expect(reset).toHaveBeenCalled();
    });

    it('wraps string beacon payloads in a JSON blob', async () => {
        const sendBeacon = jest.fn(() => true);
        const flush = jest.fn(() => {
            navigator.sendBeacon('https://gateway.example/v1/event', JSON.stringify({ events: [{ event_type: 'logout' }] }));
        });
        const reset = jest.fn();
        const addOnLogoutListener = jest.fn();

        Shopware.Service = jest.fn(() => ({
            addOnLogoutListener,
        }));

        const originalSendBeacon = navigator.sendBeacon;
        navigator.sendBeacon = sendBeacon;

        registerAmplitudeLogoutListener(
            {
                flush,
                reset,
                setTransport: jest.fn(),
            },
            'https://gateway.example',
        );

        jest.useFakeTimers();
        addOnLogoutListener.mock.calls[0][0]();
        jest.runAllTimers();

        const payload = JSON.stringify({ events: [{ event_type: 'logout' }] });

        expect(sendBeacon).toHaveBeenCalledTimes(1);
        expect(sendBeacon).toHaveBeenCalledWith('https://gateway.example/v1/event', expect.any(Blob));
        await expect(sendBeacon.mock.calls[0][1].text()).resolves.toBe(payload);
        expect(reset).toHaveBeenCalledTimes(1);

        navigator.sendBeacon = originalSendBeacon;
    });

    it('leaves unrelated beacon endpoints untouched', () => {
        const sendBeacon = jest.fn(() => true);
        const addOnLogoutListener = jest.fn();

        Shopware.Service = jest.fn(() => ({
            addOnLogoutListener,
        }));

        const originalSendBeacon = navigator.sendBeacon;
        navigator.sendBeacon = sendBeacon;

        registerAmplitudeLogoutListener(
            {
                flush: jest.fn(() => {
                    navigator.sendBeacon('https://gateway.example/other-endpoint', 'plain-string');
                }),
                reset: jest.fn(),
                setTransport: jest.fn(),
            },
            'https://gateway.example',
        );

        jest.useFakeTimers();
        addOnLogoutListener.mock.calls[0][0]();
        jest.runAllTimers();

        expect(sendBeacon).toHaveBeenCalledWith('https://gateway.example/other-endpoint', 'plain-string');

        navigator.sendBeacon = originalSendBeacon;
    });

    it('restores the native sendBeacon after overlapping logout callbacks', async () => {
        const sendBeacon = jest.fn(() => true);
        const flush = jest.fn(() => {
            navigator.sendBeacon('https://gateway.example/v1/event', JSON.stringify({ events: [{ event_type: 'logout' }] }));
        });
        const addOnLogoutListener = jest.fn();

        Shopware.Service = jest.fn(() => ({
            addOnLogoutListener,
        }));

        const originalSendBeacon = navigator.sendBeacon;
        navigator.sendBeacon = sendBeacon;

        registerAmplitudeLogoutListener(
            {
                flush,
                reset: jest.fn(),
                setTransport: jest.fn(),
            },
            'https://gateway.example',
        );

        jest.useFakeTimers();
        const logoutListener = addOnLogoutListener.mock.calls[0][0];

        logoutListener();
        logoutListener();
        jest.runAllTimers();

        expect(sendBeacon).toHaveBeenCalledTimes(2);
        await expect(sendBeacon.mock.calls[0][1].text()).resolves.toBe(
            JSON.stringify({ events: [{ event_type: 'logout' }] }),
        );
        await expect(sendBeacon.mock.calls[1][1].text()).resolves.toBe(
            JSON.stringify({ events: [{ event_type: 'logout' }] }),
        );

        navigator.sendBeacon('https://gateway.example/event', 'plain-string');

        expect(sendBeacon).toHaveBeenLastCalledWith('https://gateway.example/event', 'plain-string');

        navigator.sendBeacon = originalSendBeacon;
    });
});
