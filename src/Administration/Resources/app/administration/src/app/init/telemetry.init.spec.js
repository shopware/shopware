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

    it('tracks the event with data and resolved source', () => {
        handler({ event: 'button_clicked', data: { sw_element_id: 'save' } }, { _event_: { origin: 'http://my-plugin.example.com' } });

        expect(trackSpy).toHaveBeenCalledWith({
            eventName: 'button_clicked',
            sw_element_id: 'save',
            source: 'my-plugin',
        });
    });

    it('falls back to "unknown" when origin does not match any extension', () => {
        handler({ event: 'some_event' }, { _event_: { origin: 'http://unknown.example.com' } });

        expect(trackSpy).toHaveBeenCalledWith(expect.objectContaining({ source: 'unknown' }));
    });

    it('omits data properties when payload has no data', () => {
        handler({ event: 'simple_event' }, { _event_: { origin: 'http://my-plugin.example.com' } });

        expect(trackSpy).toHaveBeenCalledWith({
            eventName: 'simple_event',
            source: 'my-plugin',
        });
    });
});
