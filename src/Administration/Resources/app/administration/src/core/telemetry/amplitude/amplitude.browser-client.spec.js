import {
    createPrivacyAmplitudeClient,
    getAmplitudeBrowserApiKeyPrefix,
    initTelemetryAmplitude,
    registerTelemetryLogoutListener,
} from './amplitude.browser-client';

describe('src/core/telemetry/amplitude/amplitude.browser-client.ts', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        Shopware.Store.get('context').app.config.version = '6.7.0.0';
    });

    it('registers the logout listener and resets amplitude after flushing', () => {
        const flush = jest.fn();
        const reset = jest.fn();
        const setTransport = jest.fn();
        const addOnLogoutListener = jest.fn();

        Shopware.Service = jest.fn(() => ({
            addOnLogoutListener,
        }));

        registerTelemetryLogoutListener({
            flush,
            reset,
            setTransport,
        });

        jest.useFakeTimers();
        addOnLogoutListener.mock.calls[0][0]();

        expect(setTransport).toHaveBeenCalledWith('beacon');

        jest.runAllTimers();

        expect(flush).toHaveBeenCalled();
        expect(reset).toHaveBeenCalled();
        jest.useRealTimers();
    });

    it('initializes telemetry amplitude against the gateway event endpoint', () => {
        const init = jest.fn();

        initTelemetryAmplitude({ init }, 'https://gateway.example');

        expect(init).toHaveBeenCalledWith(
            'placeholder-apikey',
            undefined,
            expect.objectContaining({
                serverUrl: 'https://gateway.example/event',
                appVersion: '6.7.0.0',
                autocapture: false,
                serverZone: 'EU',
                flushMaxRetries: 2,
                logLevel: 0,
                fetchRemoteConfig: false,
                trackingOptions: {
                    ipAddress: false,
                    language: false,
                    platform: false,
                },
            }),
        );
    });

    it('creates a privacy amplitude client for delete-user requests', () => {
        const privacyAmplitude = {
            init: jest.fn(),
        };
        const createInstance = jest.fn(() => privacyAmplitude);

        const result = createPrivacyAmplitudeClient({ createInstance }, 'https://gateway.example');

        expect(createInstance).toHaveBeenCalled();
        expect(privacyAmplitude.init).toHaveBeenCalledWith(
            'placeholder-apikey',
            undefined,
            expect.objectContaining({
                serverUrl: 'https://gateway.example/delete-user',
            }),
        );
        expect(result).toBe(privacyAmplitude);
    });

    it('returns the browser api key prefix used for cookie cleanup', () => {
        expect(getAmplitudeBrowserApiKeyPrefix()).toBe('placeholde');
    });
});
