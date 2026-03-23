import { AmplitudeAdapter } from './amplitude-adapter';
jest.mock('@amplitude/analytics-browser', () => ({
    createInstance: jest.fn().mockReturnValue({
        add: jest.fn(),
        init: jest.fn(),
        reset: jest.fn(),
        track: jest.fn(),
        flush: jest.fn(),
        setOptOut: jest.fn(),
        setTransport: jest.fn(),
        getUserId: jest.fn(),
        setUserId: jest.fn(),
    }),
    Types: { LogLevel: { None: 0 } },
}));

describe('src/core/telemetry/product-analytics/amplitude-adapter', () => {
    let mockInstance;

    beforeEach(async () => {
        jest.clearAllMocks();

        mockInstance = (await import('@amplitude/analytics-browser')).createInstance();

        // Mock global Shopware store used for app version
        Shopware.Store.get('context').app.config.version = '6.8.0.0';
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('initializes amplitude instance only once and passes serverUrl and appVersion', () => {
        const adapter = new AmplitudeAdapter('https://my.server', 'en');

        adapter.init();
        adapter.init(); // second call should be no-op

        expect(mockInstance.add).toHaveBeenCalledTimes(1);
        expect(mockInstance.add).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'DefaultShopwareProperties',
            }),
        );

        expect(mockInstance.init).toHaveBeenCalledTimes(1);
        expect(mockInstance.init).toHaveBeenCalledWith(
            'placeholder-apikey',
            undefined,
            expect.objectContaining({
                serverUrl: 'https://my.server/v1/event',
                appVersion: '6.8.0.0',
            }),
        );
    });

    it('identifies and returns user id', () => {
        const adapter = new AmplitudeAdapter('https://s', 'en');

        adapter.identify('user-123');
        expect(mockInstance.setUserId).toHaveBeenCalledWith('user-123');

        mockInstance.getUserId.mockReturnValue('user-123');
        expect(adapter.getUserId()).toBe('user-123');

        mockInstance.getUserId.mockReturnValue(undefined);
        expect(adapter.getUserId()).toBeNull();
    });

    it('forwards track and other controls to amplitude instance', () => {
        const adapter = new AmplitudeAdapter('https://s', 'en');

        adapter.reset();
        expect(mockInstance.reset).toHaveBeenCalled();

        adapter.flush();
        expect(mockInstance.flush).toHaveBeenCalled();

        adapter.setOptOut(true);
        expect(mockInstance.setOptOut).toHaveBeenCalledWith(true);

        adapter.setTransport('xhr');
        expect(mockInstance.setTransport).toHaveBeenCalledWith('xhr');
    });

    it('tracks events only when initialized', () => {
        const adapter = new AmplitudeAdapter('https://s', 'en');

        adapter.track('evt', { a: 1 });
        expect(mockInstance.track).not.toHaveBeenCalled();

        adapter.init();

        adapter.track('evt', { a: 1 });
        expect(mockInstance.track).toHaveBeenCalledWith('evt', { a: 1 });
    });

    it('clears amplitude cookies using provided storage with base path', () => {
        document.cookie = 'AMP_placeholde=test-value';
        document.cookie = 'AMP_MKTG_placeholde=test-value';
        document.cookie = 'other-cookie=test-value';

        const adapter = new AmplitudeAdapter('https://s', 'en');

        adapter.clearStorage();

        expect(document.cookie).not.toContain('AMP_placeholde=');
        expect(document.cookie).not.toContain('AMP_MKTG_placeholde=');
        expect(document.cookie).toContain('other-cookie=test-value');
    });
});
