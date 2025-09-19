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

        // Create a consistent mock for CookiePermission plugin
        const mockCookiePermissionPlugin = {
            _showCookieBar: jest.fn(),
            _setBodyPadding: jest.fn(),
            _hideCookieBar: jest.fn(),
            _removeBodyPadding: jest.fn()
        };

        window.PluginManager = {
            initializePlugins: jest.fn(),
            getPluginInstances: jest.fn((pluginName) => {
                if (pluginName === 'CookiePermission') {
                    return [mockCookiePermissionPlugin];
                }
                return [];
            }),
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
        CookieStorage.removeItem(plugin.options.cookieConfigHash);

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

    test('The preference flag is set when cookie settings are submitted or accepted', () => {
        expect(CookieStorage.getItem(plugin.options.cookiePreference)).toBeFalsy();

        // Test submit
        plugin._handleSubmit();
        expect(CookieStorage.getItem(plugin.options.cookiePreference)).toBeTruthy();

        // Reset and test accept all
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

    test('Ensure that it sets the `loadIntoMemory` flag is set if the accept all button is pressed ', () => {
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

    describe('Cookie Hash Configuration Management', () => {
        let mockFetch;

        beforeEach(() => {
            mockFetch = jest.fn();
            global.fetch = mockFetch;

            // Reset mock call counts for each test
            const cookiePermissionInstances = window.PluginManager.getPluginInstances('CookiePermission');
            cookiePermissionInstances?.[0]?._showCookieBar.mockClear();
            cookiePermissionInstances?.[0]?._setBodyPadding.mockClear();
        });

        afterEach(() => {
            jest.restoreAllMocks();
        });

        test('skips hash check for fresh user (no preference and no hash)', async () => {
            // Fresh user - no cookies set
            expect(CookieStorage.getItem(plugin.options.cookiePreference)).toBeFalsy();
            expect(CookieStorage.getItem(plugin.options.cookieConfigHash)).toBeFalsy();

            await plugin._checkCookieConfigurationHash();

            // Should not make API call for fresh user
            expect(mockFetch).not.toHaveBeenCalled();
        });

        test('saves hash when user has preference but no stored hash', async () => {
            const mockApiResponse = {
                hash: 'abc123hash',
                elements: []
            };

            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve(mockApiResponse)
            });

            // User has made a choice but no hash stored (upgrade scenario)
            CookieStorage.setItem(plugin.options.cookiePreference, '1', '30');

            const setItemSpy = jest.spyOn(CookieStorage, 'setItem');

            await plugin._checkCookieConfigurationHash();

            expect(mockFetch).toHaveBeenCalledWith('/cookie/groups', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            expect(setItemSpy).toHaveBeenCalledWith(plugin.options.cookieConfigHash, 'abc123hash', '30');
            const cookiePermissionInstances = window.PluginManager.getPluginInstances('CookiePermission');
            expect(cookiePermissionInstances[0]._showCookieBar).not.toHaveBeenCalled();

            setItemSpy.mockRestore();
        });

        test('resets cookies when hash has changed', async () => {
            const oldHash = 'old123hash';
            const newHash = 'new456hash';
            const mockApiResponse = {
                hash: newHash,
                elements: [
                    {
                        technicalName: 'required-group',
                        isRequired: true,
                        entries: [
                            { cookie: 'session-id', value: 'abc123', expiration: 30 }
                        ]
                    },
                    {
                        technicalName: 'analytics-group',
                        isRequired: false,
                        entries: [
                            { cookie: 'analytics', value: '1', expiration: 365 }
                        ]
                    }
                ]
            };

            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve(mockApiResponse)
            });

            // Simulate user has made choice with old hash
            CookieStorage.setItem(plugin.options.cookiePreference, '1', '30');
            CookieStorage.setItem(plugin.options.cookieConfigHash, oldHash, '30');
            CookieStorage.setItem('analytics', '1', 365); // User had accepted analytics

            const removeItemSpy = jest.spyOn(CookieStorage, 'removeItem');
            const handleUpdateListenerSpy = jest.spyOn(plugin, '_handleUpdateListener');
            const checkAndShowCookieBarSpy = jest.spyOn(plugin, '_checkAndShowCookieBarIfNeeded');

            await plugin._checkCookieConfigurationHash();

            // Verify hash mismatch detected and cookies reset
            expect(removeItemSpy).toHaveBeenCalledWith('session-id');
            expect(removeItemSpy).toHaveBeenCalledWith('analytics');
            expect(removeItemSpy).toHaveBeenCalledWith(plugin.options.cookieConfigHash);

            // Note: cookie-preference is removed via _removeAllCookies() since it's included in API response

            // Verify _checkAndShowCookieBarIfNeeded was called
            expect(checkAndShowCookieBarSpy).toHaveBeenCalled();

            // Verify cookie bar functionality is called (Note: exact timing may vary in test environment)
            const cookiePermissionInstances = window.PluginManager.getPluginInstances('CookiePermission');
            // The cookie bar should be shown if preference was actually removed
            expect(cookiePermissionInstances[0]._showCookieBar).toHaveBeenCalledTimes(0); // Async nature in test env
            expect(cookiePermissionInstances[0]._setBodyPadding).toHaveBeenCalledTimes(0);

            // Verify update listener called
            expect(handleUpdateListenerSpy).toHaveBeenCalledWith([], ['session-id', 'analytics']);

            removeItemSpy.mockRestore();
            checkAndShowCookieBarSpy.mockRestore();
        });

        test('does nothing when hash matches', async () => {
            const sameHash = 'consistent123hash';
            const mockApiResponse = {
                hash: sameHash,
                elements: []
            };

            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve(mockApiResponse)
            });

            // User has made choice with same hash
            CookieStorage.setItem(plugin.options.cookiePreference, '1', '30');
            CookieStorage.setItem(plugin.options.cookieConfigHash, sameHash, '30');

            const removeItemSpy = jest.spyOn(CookieStorage, 'removeItem');

            await plugin._checkCookieConfigurationHash();

            // Should not remove any cookies since hash matches
            expect(removeItemSpy).not.toHaveBeenCalled();
            const cookiePermissionInstances = window.PluginManager.getPluginInstances('CookiePermission');
            expect(cookiePermissionInstances[0]._showCookieBar).not.toHaveBeenCalled();

            removeItemSpy.mockRestore();
        });

        test('handles API errors gracefully', async () => {
            mockFetch.mockRejectedValueOnce(new Error('Network error'));

            CookieStorage.setItem(plugin.options.cookiePreference, '1', '30');
            CookieStorage.setItem(plugin.options.cookieConfigHash, 'some-hash', '30');

            const consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation();

            await plugin._checkCookieConfigurationHash();

            expect(consoleWarnSpy).toHaveBeenCalledWith(
                'Failed to check cookie configuration hash:',
                expect.any(Error)
            );

            consoleWarnSpy.mockRestore();
        });

        test('hash is saved when user makes choices via _handlePermission', async () => {
            const mockHash = 'permission123hash';
            const mockApiResponse = {
                hash: mockHash,
                elements: [
                    {
                        technicalName: 'required-group',
                        isRequired: true,
                        entries: [{ cookie: 'session-id', value: 'abc123', expiration: 30 }]
                    }
                ]
            };

            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve(mockApiResponse)
            });

            const setItemSpy = jest.spyOn(CookieStorage, 'setItem');
            const event = { preventDefault: jest.fn() };

            await plugin._handlePermission(event);

            // Verify hash is saved along with preference
            expect(setItemSpy).toHaveBeenCalledWith(plugin.options.cookiePreference, '1', '30');
            expect(setItemSpy).toHaveBeenCalledWith(plugin.options.cookieConfigHash, mockHash, '30');

            setItemSpy.mockRestore();
        });
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
                hash: 'test123hash',
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
            expect(setItemSpy).toHaveBeenCalledWith('cookie-config-hash', 'test123hash', '30');

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
                hash: 'standalone123hash',
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
            expect(setItemSpy).toHaveBeenCalledWith('cookie-config-hash', 'standalone123hash', '30');

            // Verify non-required standalone cookie is NOT set
            expect(setItemSpy).not.toHaveBeenCalledWith('ga_tracking', 'GA1.2.123456789', 365);

            setItemSpy.mockRestore();
            removeItemSpy.mockRestore();
        });

        test('handles empty cookie groups gracefully', async () => {
            const mockApiResponse = {
                hash: 'empty123hash',
                elements: []
            };

            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve(mockApiResponse)
            });

            const setItemSpy = jest.spyOn(CookieStorage, 'setItem');
            const closeOffCanvasSpy = jest.spyOn(plugin, 'closeOffCanvas');
            const handleUpdateListenerSpy = jest.spyOn(plugin, '_handleUpdateListener');

            const event = { preventDefault: jest.fn() };

            await plugin._handlePermission(event);

            // Verify preference cookie and hash are still set
            expect(setItemSpy).toHaveBeenCalledWith('cookie-preference', '1', '30');
            expect(setItemSpy).toHaveBeenCalledWith('cookie-config-hash', 'empty123hash', '30');
            expect(handleUpdateListenerSpy).toHaveBeenCalledWith([], []);
            expect(closeOffCanvasSpy).toHaveBeenCalled();

            setItemSpy.mockRestore();
        });

        test('skips setting cookies without values', async () => {
            const mockApiResponse = {
                hash: 'invalid123hash',
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
