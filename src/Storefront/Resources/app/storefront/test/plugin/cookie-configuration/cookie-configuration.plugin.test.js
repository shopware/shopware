import CookieStorage from 'src/helper/storage/cookie-storage.helper';
import CookieConfiguration, { COOKIE_CONFIGURATION_UPDATE } from 'src/plugin/cookie/cookie-configuration.plugin';
import AjaxOffCanvas from 'src/plugin/offcanvas/ajax-offcanvas.plugin';

const template = `
    <div class="offcanvas-cookie">
    <div class="offcanvas-cookie-description"></div>

    <div class="offcanvas-cookie-list">
        <div class="offcanvas-cookie-group">

            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input offcanvas-cookie-parent-input" id="cookie_Technically required" checked="checked" disabled="disabled" data-cookie-required="true">
            </div>

            <div class="offcanvas-cookie-entries">

                <div class="offcanvas-cookie-entry custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="cookie_session-" checked="checked" disabled="disabled" data-cookie-required="true" data-cookie="session-">
                </div>

                <div class="offcanvas-cookie-entry custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="cookie_timezone" checked="checked" disabled="disabled" data-cookie-required="true" data-cookie="timezone">
                </div>

            </div>

        </div>

        <div class="offcanvas-cookie-group">

            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input offcanvas-cookie-parent-input" id="cookie_Statistics">
            </div>

            <div class="offcanvas-cookie-entries">
                <div class="offcanvas-cookie-entry custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="cookie_lorem" data-cookie="lorem" data-cookie-value="1" data-cookie-expiration="30">
                </div>

                <div class="offcanvas-cookie-entry custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="cookie_ipsum" data-cookie="ipsum" data-cookie-value="1" data-cookie-expiration="30">
                </div>

                <div class="offcanvas-cookie-entry custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="cookie_dolor" data-cookie="dolor" data-cookie-value="1" data-cookie-expiration="30">
                </div>

                <div class="offcanvas-cookie-entry custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="cookie_sit" data-cookie="sit" data-cookie-value="1" data-cookie-expiration="30">
                </div>
            </div>

        </div>

    </div>

    <button type="submit" class="btn btn-primary btn-block js-offcanvas-cookie-submit"></button>
    <button type="submit" class="btn btn-primary btn-block js-offcanvas-cookie-accept-all"></button>
</div>
`;

describe('CookieConfiguration plugin tests', () => {
    let plugin;

    beforeEach(() => {
        window.router = {
            'frontend.cookie.offcanvas': 'https://shop.example.com/offcanvas',
            'frontend.account.login.page': 'https://shop.example.com/login',
        };

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
        };

        window.PluginManager = {
            initializePlugins: jest.fn(),
            getPluginInstances: jest.fn(() => []),
            getPluginInstancesFromElement: jest.fn(() => new Map()),
            getPlugin: jest.fn(() => new Map([['instances', []]]))
        };

        global.fetch = jest.fn(() =>
            Promise.resolve({
                text: () => Promise.resolve(template),
            })
        );

        const container = document.createElement('div');
        plugin = new CookieConfiguration(container);

        plugin.openOffCanvas(() => {});

        jest.spyOn(AjaxOffCanvas, 'open').mockImplementation(jest.fn());
        jest.spyOn(AjaxOffCanvas, 'close').mockImplementation(jest.fn());
    });

    afterEach(() => {
        const cookies = plugin._getCookies('all');

        cookies.forEach(el => {
            CookieStorage.removeItem(el.cookie);
        });
        CookieStorage.removeItem(plugin.options.cookiePreference);

        document.$emitter.unsubscribe(COOKIE_CONFIGURATION_UPDATE);

        plugin = undefined;
    });

    test('The cookie configuration plugin can be instantiated', () => {
        expect(plugin).toBeInstanceOf(CookieConfiguration);
    });

    /* eslint-disable-next-line max-len */
    test('Ensure no previously inactive cookies have been set after the "submit" handler was executed without selection', () => {
        const cookies = plugin._getCookies('inactive');

        plugin._handleSubmit();

        cookies.forEach(val => {
            expect(CookieStorage.getItem(val.cookie)).toBeFalsy();
        });
    });

    test('Ensure all previously inactive cookies have been set after the "allow all" handler was executed', () => {
        const cookies = plugin._getCookies('inactive');

        plugin._handleAcceptAll();

        cookies.forEach(val => {
            expect(CookieStorage.getItem(val.cookie)).toBeTruthy();
        });
    });

    test('The preference flag is set when cookie settings are submitted or all cookies are accepted', () => {
        expect(CookieStorage.getItem(plugin.options.cookiePreference)).toBeFalsy();

        plugin._handleSubmit();
        expect(CookieStorage.getItem(plugin.options.cookiePreference)).toBeTruthy();

        // Reset for second test
        CookieStorage.removeItem(plugin.options.cookiePreference);
        expect(CookieStorage.getItem(plugin.options.cookiePreference)).toBeFalsy();

        plugin._handleAcceptAll();
        expect(CookieStorage.getItem(plugin.options.cookiePreference)).toBeTruthy();
    });

    test('Ensure the COOKIE_CONFIGURATION_UPDATE event is fired with all previously inactive cookies', done => {
        const cookies = plugin._getCookies('inactive');

        function cb(event) {
            try {
                expect(Object.keys(event.detail)).toHaveLength(cookies.length);

                Object.keys(event.detail).forEach(key => {
                    expect(cookies.find(({ cookie }) => cookie === key)).toBeTruthy();
                });

                done();
            } catch (err) {
                done(err);
            }
        }

        document.$emitter.subscribe(COOKIE_CONFIGURATION_UPDATE, cb);

        plugin._handleAcceptAll();
    });

    test('Ensure handleCustomLink opens the off-canvas-menu', () => {
        const openOffCanvas = jest.spyOn(plugin, 'openOffCanvas');

        plugin._handleCustomLink({ preventDefault: () => {} });

        expect(openOffCanvas).toHaveBeenCalled();
    });

    test('Ensure the plugin is initialised when the off-canvas-panel is opened', () => {
        const setInitialState = jest.spyOn(plugin, '_setInitialState');

        plugin._onOffCanvasOpened(jest.fn());

        expect(setInitialState).toHaveBeenCalled();
    });

    test('Ensure _setInitialState reads the correct state from the template', () => {
        // These cookies are represented in the offcanvas.template.html
        const requiredAndActive = ['session-', 'timezone'];
        const optionalAndInactive = ['lorem', 'ipsum', 'dolor', 'sit'];

        delete plugin.lastState;

        expect(plugin.lastState).not.toBeDefined();

        plugin._setInitialState();

        expect(plugin.lastState).toBeDefined();
        expect(plugin.lastState.active).toEqual(requiredAndActive);
        expect(plugin.lastState.inactive).toEqual(optionalAndInactive);
    });

    test('Ensure cookies deactivated by the user are removed when the preferences are submitted', () => {
        // These cookies are represented in the offcanvas.template.html
        const requiredAndActive = ['session-', 'timezone'];
        const optionalAndInactive = ['lorem', 'ipsum', 'dolor', 'sit'];
        const checkbox = document.body.querySelector(`[data-cookie="${optionalAndInactive[0]}"]`);

        delete plugin.lastState;

        CookieStorage.setItem(optionalAndInactive[0], optionalAndInactive[0], 30);

        plugin._setInitialState();
        plugin._setInitialOffcanvasState();

        expect(plugin.lastState.active).toEqual([...requiredAndActive, optionalAndInactive[0]]);
        expect(CookieStorage.getItem(optionalAndInactive[0])).toBeTruthy();
        expect(checkbox.checked).toBeTruthy();

        checkbox.checked = false;

        expect(checkbox.checked).toBeFalsy();

        plugin._handleSubmit();

        expect(CookieStorage.getItem(optionalAndInactive[0])).toBeFalsy();
    });

    test('Ensure that loadIntoMemory flag triggers fetch when accept all button is pressed from cookie bar', () => {
        plugin._acceptAllCookiesFromCookieBar();

        expect(global.fetch).toHaveBeenCalledWith('https://shop.example.com/offcanvas', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    });

    test('openRequestConsentOffCanvas sets lastTriggerElement and calls AjaxOffCanvas.open', () => {
        document.body.innerHTML += '<button id="wishlist-btn">Add to wishlist</button>';
        const triggerBtn = document.getElementById('wishlist-btn');
        triggerBtn.focus();
        plugin.openRequestConsentOffCanvas('/cookie/consent-offcanvas', 'wishlist-enabled');
        expect(CookieConfiguration.lastTriggerElement).toBe(triggerBtn);
        expect(AjaxOffCanvas.open).toHaveBeenCalledWith(
            '/cookie/consent-offcanvas',
            false,
            expect.any(Function),
            'left'
        );
        document.body.removeChild(triggerBtn);
    });

    test('_onAccept sets the cookie and closes the offcanvas', () => {
        const setItemSpy = jest.spyOn(CookieStorage, 'setItem').mockImplementation(jest.fn());
        plugin._onAccept('wishlist-enabled');
        expect(setItemSpy).toHaveBeenCalledWith('wishlist-enabled', '1', 30);
        expect(AjaxOffCanvas.close).toHaveBeenCalled();
        setItemSpy.mockRestore();
    });

    test('_onLogin closes the offcanvas and redirects', () => {
        const originalLocation = window.location;
        delete window.location;
        window.location = { href: '' };
        window.router['frontend.account.login.page'] = 'https://shop.example.com/login';
        plugin._onLogin();
        expect(AjaxOffCanvas.close).toHaveBeenCalled();
        expect(window.location.href).toBe('https://shop.example.com/login');
        window.location = originalLocation;
    });

    test('_onCancel closes the offcanvas', () => {
        plugin._onCancel();
        expect(AjaxOffCanvas.close).toHaveBeenCalled();
    });

    test('_onPreferences closes the offcanvas and opens config modal', () => {
        const openOffCanvasSpy = jest.spyOn(plugin, 'openOffCanvas');
        const event = { preventDefault: jest.fn() };
        plugin._onPreferences(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(AjaxOffCanvas.close).toHaveBeenCalled();
        expect(openOffCanvasSpy).toHaveBeenCalled();
    });

    test('openRequestConsentOffCanvas does not throw exception if .offcanvas is missing', () => {
        plugin._getOffCanvas = jest.fn(() => ({
            querySelectorAll: () => [],
        }));
        expect(() => {
            plugin.openRequestConsentOffCanvas('/cookie/consent-offcanvas', 'wishlist-enabled');
        }).not.toThrow();
        expect(AjaxOffCanvas.open).toHaveBeenCalled();
    });

    test('_restoreFocus focuses the lastTriggerElement', () => {
        const btn = document.createElement('button');
        document.body.appendChild(btn);
        CookieConfiguration.lastTriggerElement = btn;
        const focusSpy = jest.spyOn(btn, 'focus');
        plugin._restoreFocus();
        expect(focusSpy).toHaveBeenCalled();
        document.body.removeChild(btn);
    });

    describe('_handlePermission', () => {
        let mockFetch;

        beforeEach(() => {
            mockFetch = jest.fn();
            global.fetch = mockFetch;
        });

        afterEach(() => {
            jest.restoreAllMocks();
        });

        test('calls storefront route and sets only technical required cookies', async () => {
            const mockApiResponse = {
                elements: [
                    {
                        technicalName: 'required-group',
                        isRequired: true,
                        entries: [
                            {
                                cookie: 'session-id',
                                value: 'abc123',
                                expiration: 30
                            },
                            {
                                cookie: 'csrf-token',
                                value: 'xyz789',
                                expiration: 30
                            }
                        ]
                    },
                    {
                        technicalName: 'marketing-group',
                        isRequired: false,
                        entries: [
                            {
                                cookie: 'analytics',
                                value: '1',
                                expiration: 365
                            },
                            {
                                cookie: 'tracking',
                                value: '1',
                                expiration: 90
                            }
                        ]
                    }
                ]
            };

            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve(mockApiResponse)
            });

            const setItemSpy = jest.spyOn(CookieStorage, 'setItem');
            const removeItemSpy = jest.spyOn(CookieStorage, 'removeItem');
            const closeOffCanvasSpy = jest.spyOn(plugin, 'closeOffCanvas');
            const handleUpdateListenerSpy = jest.spyOn(plugin, '_handleUpdateListener');

            const event = { preventDefault: jest.fn() };

            await plugin._handlePermission(event);

            // Verify API call
            expect(mockFetch).toHaveBeenCalledWith('/cookie/groups', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            // Verify all cookies are removed
            expect(removeItemSpy).toHaveBeenCalledWith('session-id');
            expect(removeItemSpy).toHaveBeenCalledWith('csrf-token');
            expect(removeItemSpy).toHaveBeenCalledWith('analytics');
            expect(removeItemSpy).toHaveBeenCalledWith('tracking');

            // Verify only required cookies are set
            expect(setItemSpy).toHaveBeenCalledWith('session-id', 'abc123', 30);
            expect(setItemSpy).toHaveBeenCalledWith('csrf-token', 'xyz789', 30);
            expect(setItemSpy).toHaveBeenCalledWith('cookie-preference', '1', '30');

            // Verify non-required cookies are NOT set
            expect(setItemSpy).not.toHaveBeenCalledWith('analytics', '1', 365);
            expect(setItemSpy).not.toHaveBeenCalledWith('tracking', '1', 90);

            // Verify update listener called with correct parameters
            expect(handleUpdateListenerSpy).toHaveBeenCalledWith(
                ['session-id', 'csrf-token'], // active (required)
                ['analytics', 'tracking'] // inactive (non-required)
            );

            // Verify offcanvas closes
            expect(closeOffCanvasSpy).toHaveBeenCalled();

            setItemSpy.mockRestore();
            removeItemSpy.mockRestore();
        });

        test('handles standalone cookie groups correctly', async () => {
            const mockApiResponse = {
                elements: [
                    {
                        technicalName: 'session-cookie',
                        isRequired: true,
                        cookie: 'PHPSESSID',
                        value: 'session123',
                        expiration: 30
                    },
                    {
                        technicalName: 'analytics-cookie',
                        isRequired: false,
                        cookie: 'ga_tracking',
                        value: 'GA1.2.123456789',
                        expiration: 365
                    }
                ]
            };

            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve(mockApiResponse)
            });

            const setItemSpy = jest.spyOn(CookieStorage, 'setItem');
            const removeItemSpy = jest.spyOn(CookieStorage, 'removeItem');

            const event = { preventDefault: jest.fn() };

            await plugin._handlePermission(event);

            // Verify all cookies are removed first
            expect(removeItemSpy).toHaveBeenCalledWith('PHPSESSID');
            expect(removeItemSpy).toHaveBeenCalledWith('ga_tracking');

            // Verify only required standalone cookie is set
            expect(setItemSpy).toHaveBeenCalledWith('PHPSESSID', 'session123', 30);
            expect(setItemSpy).toHaveBeenCalledWith('cookie-preference', '1', '30');

            // Verify non-required standalone cookie is NOT set
            expect(setItemSpy).not.toHaveBeenCalledWith('ga_tracking', 'GA1.2.123456789', 365);

            setItemSpy.mockRestore();
            removeItemSpy.mockRestore();
        });

        test('handles empty cookie groups gracefully', async () => {
            const mockApiResponse = { elements: [] };

            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve(mockApiResponse)
            });

            const setItemSpy = jest.spyOn(CookieStorage, 'setItem');
            const closeOffCanvasSpy = jest.spyOn(plugin, 'closeOffCanvas');
            const handleUpdateListenerSpy = jest.spyOn(plugin, '_handleUpdateListener');

            const event = { preventDefault: jest.fn() };

            await plugin._handlePermission(event);

            // Verify preference cookie is still set
            expect(setItemSpy).toHaveBeenCalledWith('cookie-preference', '1', '30');
            expect(handleUpdateListenerSpy).toHaveBeenCalledWith([], []);
            expect(closeOffCanvasSpy).toHaveBeenCalled();

            setItemSpy.mockRestore();
        });

        test('skips setting cookies without values', async () => {
            const mockApiResponse = {
                elements: [
                    {
                        technicalName: 'required-group',
                        isRequired: true,
                        entries: [
                            {
                                cookie: 'valid-cookie',
                                value: 'abc123',
                                expiration: 30
                            },
                            {
                                cookie: 'invalid-cookie',
                                value: null, // No value
                                expiration: 30
                            }
                        ]
                    }
                ]
            };

            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve(mockApiResponse)
            });

            const setItemSpy = jest.spyOn(CookieStorage, 'setItem');

            const event = { preventDefault: jest.fn() };

            await plugin._handlePermission(event);

            // Verify only valid cookie is set
            expect(setItemSpy).toHaveBeenCalledWith('valid-cookie', 'abc123', 30);
            expect(setItemSpy).not.toHaveBeenCalledWith('invalid-cookie', null, 30);

            setItemSpy.mockRestore();
        });
    });
});
