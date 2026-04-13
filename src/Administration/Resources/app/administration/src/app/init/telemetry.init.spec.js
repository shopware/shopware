import initializeTelemetry from './telemetry.init';

describe('src/app/init/telemetry.init.ts', () => {
    let handler;
    let trackSpy;

    beforeEach(() => {
        handler = null;
        jest.spyOn(Shopware.ExtensionAPI, 'handle').mockImplementation((name, cb) => {
            if (name === 'telemetryDispatch') handler = cb;
        });
        trackSpy = jest.spyOn(Shopware.Telemetry, 'track').mockImplementation(() => {});

        window._swsdk = {
            ...window._swsdk,
            adminExtensions: {
                'my-plugin': { baseUrl: 'http://my-plugin.example.com', permissions: {} },
                'local-plugin': {
                    baseUrl: `${window.location.origin}/bundles/local-plugin/`,
                    permissions: {},
                },
            },
        };

        initializeTelemetry();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('registers a telemetryDispatch handler', () => {
        expect(Shopware.ExtensionAPI.handle).toHaveBeenCalledWith('telemetryDispatch', expect.any(Function));
    });

    it('tracks the event with data and resolved source for a cross-origin extension', () => {
        handler(
            { event: 'button_clicked', data: { sw_element_id: 'save' } },
            { _event_: { origin: 'http://my-plugin.example.com', source: null } },
        );

        expect(trackSpy).toHaveBeenCalledWith({
            eventName: 'button_clicked',
            sw_element_id: 'save',
            source: 'my-plugin',
        });
    });

    it('resolves source for a same-origin extension via the sender window href', () => {
        const fakeWindow = {
            location: { href: `${window.location.origin}/bundles/local-plugin/index.html` },
        };

        handler({ event: 'page_viewed', data: {} }, { _event_: { origin: window.location.origin, source: fakeWindow } });

        expect(trackSpy).toHaveBeenCalledWith(expect.objectContaining({ source: 'local-plugin' }));
    });

    it('falls back to "unknown" when origin does not match any extension', () => {
        handler({ event: 'some_event' }, { _event_: { origin: 'http://unknown.example.com', source: null } });

        expect(trackSpy).toHaveBeenCalledWith(expect.objectContaining({ source: 'unknown' }));
    });

    it('falls back to "unknown" for same-origin when source window href does not match', () => {
        const fakeWindow = { location: { href: `${window.location.origin}/some/other/page` } };

        handler({ event: 'some_event' }, { _event_: { origin: window.location.origin, source: fakeWindow } });

        expect(trackSpy).toHaveBeenCalledWith(expect.objectContaining({ source: 'unknown' }));
    });

    it('omits data properties when payload has no data', () => {
        handler({ event: 'simple_event' }, { _event_: { origin: 'http://my-plugin.example.com', source: null } });

        expect(trackSpy).toHaveBeenCalledWith({
            eventName: 'simple_event',
            source: 'my-plugin',
        });
    });
});
