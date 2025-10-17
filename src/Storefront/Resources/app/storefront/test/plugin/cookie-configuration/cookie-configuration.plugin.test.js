import CookieStorage from 'src/helper/storage/cookie-storage.helper';
import CookieConfiguration, { COOKIE_CONFIGURATION_UPDATE } from 'src/plugin/cookie/cookie-configuration.plugin';
import AjaxOffCanvas from 'src/plugin/offcanvas/ajax-offcanvas.plugin';
import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';

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
    let mockCookiePermissionPlugin;

    beforeEach(() => {
        window.router = {
            'frontend.cookie.offcanvas': 'https://shop.example.com/offcanvas',
            'frontend.cookie.groups': 'https://shop.example.com/cookie/groups',
            'frontend.account.login.page': 'https://shop.example.com/login',
        };

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
        };

        // Create a proper mock for CookiePermission plugin
        mockCookiePermissionPlugin = {
            _showCookieBar: jest.fn(),
            _hideCookieBar: jest.fn(),
            _setBodyPadding: jest.fn(),
            _removeBodyPadding: jest.fn(),
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
                json: () => Promise.resolve({
                    hash: 'default-test-hash',
                    elements: [],
                }),
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

        cookies.forEach(el => { CookieStorage.removeItem(el.cookie); });
        CookieStorage.removeItem(plugin.options.cookiePreference);
        CookieStorage.removeItem(plugin.options.cookieConfigHash);

        document.$emitter.unsubscribe(COOKIE_CONFIGURATION_UPDATE);

        plugin = undefined;
    });

    test('The cookie configuration plugin can be instantiated', () => {
        expect(plugin).toBeInstanceOf(CookieConfiguration);
    });

    test('Ensure no previously inactive cookies have been set after the "submit" handler was executed without selection', () => {
        const cookies = plugin._getCookies('inactive');

        plugin._handleSubmit();

        cookies.forEach(val => {
            void expect(CookieStorage.getItem(val.cookie)).toBeFalsy();
        });
    });

    test('Ensure all previously inactive cookies have been set after the "allow all" handler was executed', async () => {
        const cookies = plugin._getCookies('inactive');

        // Mock API response with cookie data
        const mockCookieData = cookies.map(({ cookie }) => ({
            cookie,
            value: '1',
            expiration: 30,
        }));

        global.fetch = jest.fn().mockResolvedValue({
            json: jest.fn().mockResolvedValue({
                hash: 'test-hash',
                elements: mockCookieData,
            }),
        });

        await plugin.acceptAllCookies();

        cookies.forEach(val => {
            void expect(CookieStorage.getItem(val.cookie)).toBeTruthy();
        });

        global.fetch.mockRestore();
    });

    test('The preference flag is set when cookie settings are submitted or all cookies are accepted', async () => {
        expect(CookieStorage.getItem(plugin.options.cookiePreference)).toBeFalsy();

        // Mock API response for submit
        global.fetch = jest.fn().mockResolvedValue({
            json: jest.fn().mockResolvedValue({
                hash: 'test-hash',
                elements: [
                    {
                        isRequired: true,
                        entries: [
                            {
                                cookie: 'cookie-preference',
                                value: '1',
                                expiration: 30,
                            },
                        ],
                    },
                ],
            }),
        });

        // Test submit
        await plugin._handleSubmit();
        expect(CookieStorage.getItem(plugin.options.cookiePreference)).toBeTruthy();

        CookieStorage.removeItem(plugin.options.cookiePreference);
        expect(CookieStorage.getItem(plugin.options.cookiePreference)).toBeFalsy();

        // Test accept all
        await plugin.acceptAllCookies();
        expect(CookieStorage.getItem(plugin.options.cookiePreference)).toBeTruthy();

        global.fetch.mockRestore();
    });

    test('Ensure the COOKIE_CONFIGURATION_UPDATE event is fired with all previously inactive cookies', done => {
        const cookies = plugin._getCookies('inactive');

        function cb(event) {
            try {
                expect(Object.keys(event.detail)).toHaveLength(cookies.length);

                Object.keys(event.detail).forEach(key => {
                    void expect(cookies.find(({ cookie }) => cookie === key)).toBeTruthy();
                });

                done();
            } catch (err) {
                done(err);
            }
        }

        document.$emitter.subscribe(COOKIE_CONFIGURATION_UPDATE, cb);

        // Mock API response with cookie data
        const mockCookieData = cookies.map(({ cookie }) => ({
            cookie,
            value: '1',
            expiration: 30,
        }));

        global.fetch = jest.fn().mockResolvedValue({
            json: jest.fn().mockResolvedValue({
                hash: 'test-hash',
                elements: mockCookieData,
            }),
        });

        plugin.acceptAllCookies().catch(done);
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

    test('Ensure cookies deactivated by the user are removed when the preferences are submitted', async () => {
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

        // Mock API response for submit
        global.fetch = jest.fn().mockResolvedValue({
            json: jest.fn().mockResolvedValue({
                hash: 'test-hash',
                elements: [
                    {
                        isRequired: true,
                        entries: [
                            {
                                cookie: 'cookie-preference',
                                value: '1',
                                expiration: 30,
                            },
                        ],
                    },
                    {
                        isRequired: false,
                        entries: optionalAndInactive.map(cookie => ({
                            cookie,
                            value: '1',
                            expiration: 30,
                        })),
                    },
                ],
            }),
        });

        await plugin._handleSubmit();

        expect(CookieStorage.getItem(optionalAndInactive[0])).toBeFalsy();

        global.fetch.mockRestore();
    });

    test('Accept all button from cookie bar fetches cookie groups from API', async () => {
        const mockResponse = {
            hash: 'test-hash-123',
            elements: [
                {
                    isRequired: true,
                    entries: [
                        {
                            cookie: 'cookie-preference',
                            value: '1',
                            expiration: 30,
                        },
                        {
                            cookie: 'cookie-config-hash',
                            value: 'test-hash-123',
                            expiration: 30,
                        },
                    ],
                },
                {
                    isRequired: false,
                    entries: [
                        {
                            cookie: 'analytics',
                            value: '1',
                            expiration: 30,
                        },
                    ],
                },
            ],
        };

        global.fetch = jest.fn().mockResolvedValue({
            json: jest.fn().mockResolvedValue(mockResponse),
        });

        const setItemSpy = jest.spyOn(CookieStorage, 'setItem');

        await plugin._acceptAllCookiesFromCookieBar();

        expect(global.fetch).toHaveBeenCalledWith(window.router['frontend.cookie.groups'], {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        // Check that cookie preference was set from the entries
        expect(setItemSpy).toHaveBeenCalledWith(
            'cookie-preference',
            '1',
            30
        );

        // Check that cookie hash was set from the entries
        expect(setItemSpy).toHaveBeenCalledWith(
            'cookie-config-hash',
            'test-hash-123',
            30
        );

        setItemSpy.mockRestore();
        global.fetch.mockRestore();
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
            mockCookiePermissionPlugin._showCookieBar.mockClear();
            mockCookiePermissionPlugin._setBodyPadding.mockClear();
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

            expect(mockFetch).toHaveBeenCalledWith(window.router['frontend.cookie.groups'], {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            expect(setItemSpy).toHaveBeenCalledWith(plugin.options.cookieConfigHash, 'abc123hash', 30);
            expect(mockCookiePermissionPlugin._showCookieBar).not.toHaveBeenCalled();

            setItemSpy.mockRestore();
        });

        test('resets cookies when hash has changed and sets required cookies', async () => {
            const oldHash = 'old123hash';
            const newHash = 'new456hash';
            const mockApiResponse = {
                hash: newHash,
                elements: [
                    {
                        technicalName: 'required-group',
                        isRequired: true,
                        entries: [
                            { cookie: 'session-', value: 'abc123', expiration: 30 }, // PHP-managed, should not be set
                            { cookie: 'csrf-token', value: 'xyz789', expiration: 30 }, // Should be set
                            { cookie: 'cookie-preference', value: '1', expiration: 30 }, // Should be set
                            { cookie: 'cookie-config-hash', value: newHash, expiration: 30 } // Should be set
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

            const setItemSpy = jest.spyOn(CookieStorage, 'setItem');
            const removeItemSpy = jest.spyOn(CookieStorage, 'removeItem');
            const handleUpdateListenerSpy = jest.spyOn(plugin, '_handleUpdateListener');
            const checkAndShowCookieBarSpy = jest.spyOn(plugin, '_checkAndShowCookieBarIfNeeded');

            // Mock dispatchEvent to simulate the showCookieBar event triggering the cookie permission plugin
            const dispatchEventSpy = jest.spyOn(document, 'dispatchEvent').mockImplementation((event) => {
                if (event.type === 'showCookieBar') {
                    // Simulate the cookie permission plugin's event handler
                    mockCookiePermissionPlugin._setBodyPadding();
                    mockCookiePermissionPlugin._showCookieBar();
                }
                return true;
            });

            await plugin._checkCookieConfigurationHash();

            // Verify hash mismatch detected and only non-technically required cookies are reset
            expect(removeItemSpy).toHaveBeenCalledWith('analytics'); // not technically required, should be removed

            // Verify technically required cookies are set (excluding PHP-managed ones)
            expect(setItemSpy).toHaveBeenCalledWith('csrf-token', 'xyz789', 30); // Required but not PHP-managed
            expect(setItemSpy).toHaveBeenCalledWith('cookie-preference', '1', 30); // Required
            expect(setItemSpy).toHaveBeenCalledWith('cookie-config-hash', newHash, 30); // Required
            expect(setItemSpy).not.toHaveBeenCalledWith('session-', 'abc123', 30); // PHP-managed, should not be set

            // Verify _checkAndShowCookieBarIfNeeded was called
            expect(checkAndShowCookieBarSpy).toHaveBeenCalled();

            // Verify showCookieBar event was dispatched
            expect(dispatchEventSpy).toHaveBeenCalledWith(expect.objectContaining({
                type: 'showCookieBar'
            }));

            // Verify cookie bar functionality is called through event simulation
            expect(mockCookiePermissionPlugin._showCookieBar).toHaveBeenCalled();
            expect(mockCookiePermissionPlugin._setBodyPadding).toHaveBeenCalled();

            // Verify update listener called with shared logic behavior
            // Active: PHP-managed cookies + technically required cookies that were set (excluding cookie-preference which is explicitly removed during reset)
            // Inactive: remaining cookies (analytics in this case)
            expect(handleUpdateListenerSpy).toHaveBeenCalledWith(
                ['session-', 'timezone', 'csrf-token', 'cookie-config-hash'],
                ['analytics']
            );

            setItemSpy.mockRestore();
            removeItemSpy.mockRestore();
            checkAndShowCookieBarSpy.mockRestore();
            dispatchEventSpy.mockRestore();
        });

        test('refreshes hash when configuration matches', async () => {
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

            const setItemSpy = jest.spyOn(CookieStorage, 'setItem');
            const removeItemSpy = jest.spyOn(CookieStorage, 'removeItem');

            await plugin._checkCookieConfigurationHash();

            // Should not remove any cookies since hash matches
            expect(removeItemSpy).not.toHaveBeenCalled();
            expect(mockCookiePermissionPlugin._showCookieBar).not.toHaveBeenCalled();

            // Should refresh the hash cookie to extend expiration
            expect(setItemSpy).toHaveBeenCalledWith(plugin.options.cookieConfigHash, sameHash, 30);

            removeItemSpy.mockRestore();
            setItemSpy.mockRestore();
        });

        test('handles API errors gracefully', async () => {
            mockFetch.mockRejectedValueOnce(new Error('Network error'));

            CookieStorage.setItem(plugin.options.cookiePreference, '1', '30');
            CookieStorage.setItem(plugin.options.cookieConfigHash, 'some-hash', '30');

            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();

            await plugin._checkCookieConfigurationHash();

            expect(consoleErrorSpy).toHaveBeenCalledWith(
                'Failed to fetch cookie groups:',
                expect.any(Error)
            );

            consoleErrorSpy.mockRestore();
        });

        test('_getAllCookieNamesFromGroups extracts cookie names correctly', () => {
            const cookieGroups = [
                {
                    entries: [
                        { cookie: 'analytics' },
                        { cookie: 'tracking' }
                    ]
                },
                {
                    cookie: 'standalone-cookie'
                },
                {
                    entries: null // No entries
                },
                {
                    // No cookie or entries
                }
            ];

            const result = plugin._getAllCookieNamesFromGroups(cookieGroups);
            expect(result).toEqual(['analytics', 'tracking', 'standalone-cookie']);
        });

        test('_getTechnicallyRequiredCookieNames returns PHP-managed cookie list', () => {
            const result = plugin._getTechnicallyRequiredCookieNames();
            expect(result).toEqual(['session-', 'timezone']);
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

        test('calls storefront route and sets only required cookies', async () => {
            const mockApiResponse = {
                hash: 'test123hash',
                elements: [
                    {
                        technicalName: 'required-group',
                        isRequired: true,
                        entries: [
                            {
                                cookie: 'session-',
                                value: 'abc123',
                                expiration: 30
                            },
                            {
                                cookie: 'csrf-token',
                                value: 'xyz789',
                                expiration: 30
                            },
                            {
                                cookie: 'cookie-preference',
                                value: '1',
                                expiration: 30
                            },
                            {
                                cookie: 'cookie-config-hash',
                                value: 'test123hash',
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

            // Set non-required cookies first
            CookieStorage.setItem('analytics', '1', 365);
            CookieStorage.setItem('tracking', '1', 90);

            const setItemSpy = jest.spyOn(CookieStorage, 'setItem');
            const removeItemSpy = jest.spyOn(CookieStorage, 'removeItem');
            const closeOffCanvasSpy = jest.spyOn(plugin, 'closeOffCanvas');
            const handleUpdateListenerSpy = jest.spyOn(plugin, '_handleUpdateListener');

            const event = { preventDefault: jest.fn() };

            await plugin._handlePermission(event);

            // Verify API call
            expect(mockFetch).toHaveBeenCalledWith(window.router['frontend.cookie.groups'], {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            // Verify non-required cookies are removed
            expect(removeItemSpy).toHaveBeenCalledWith('analytics');
            expect(removeItemSpy).toHaveBeenCalledWith('tracking');

            // Verify technically required cookies are set (excluding PHP-managed ones)
            expect(setItemSpy).toHaveBeenCalledWith('csrf-token', 'xyz789', 30); // Required but not PHP-managed
            expect(setItemSpy).not.toHaveBeenCalledWith('session-', 'abc123', 30); // PHP-managed, should not be set

            // Verify preference cookies are set
            expect(setItemSpy).toHaveBeenCalledWith('cookie-preference', '1', 30);
            expect(setItemSpy).toHaveBeenCalledWith('cookie-config-hash', 'test123hash', 30);

            // Verify non-required cookies are NOT set
            expect(setItemSpy).not.toHaveBeenCalledWith('analytics', '1', 365);
            expect(setItemSpy).not.toHaveBeenCalledWith('tracking', '1', 90);

            // Verify total call count (3 required cookies: csrf-token, cookie-preference, cookie-config-hash from entries)
            expect(setItemSpy).toHaveBeenCalledTimes(3);

            // Verify update listener called with correct parameters
            expect(handleUpdateListenerSpy).toHaveBeenCalledWith(
                ['session-', 'timezone', 'csrf-token', 'cookie-preference', 'cookie-config-hash'], // active (PHP-managed + cookies we set)
                ['analytics', 'tracking'] // inactive (remaining cookies)
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
                        technicalName: 'preference-cookie',
                        isRequired: true,
                        cookie: 'cookie-preference',
                        value: '1',
                        expiration: 30
                    },
                    {
                        technicalName: 'hash-cookie',
                        isRequired: true,
                        cookie: 'cookie-config-hash',
                        value: 'standalone123hash',
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

            // Set the non-required cookie first
            CookieStorage.setItem('ga_tracking', 'GA1.2.123456789', 365);

            const setItemSpy = jest.spyOn(CookieStorage, 'setItem');
            const removeItemSpy = jest.spyOn(CookieStorage, 'removeItem');

            const event = { preventDefault: jest.fn() };

            await plugin._handlePermission(event);

            // Verify only non-required cookies are removed
            expect(removeItemSpy).toHaveBeenCalledWith('ga_tracking'); // not technically required

            // Verify technically required cookie is set (PHPSESSID is required but not PHP-managed)
            expect(setItemSpy).toHaveBeenCalledWith('PHPSESSID', 'session123', 30);

            // Verify preference cookies are set
            expect(setItemSpy).toHaveBeenCalledWith('cookie-preference', '1', 30);
            expect(setItemSpy).toHaveBeenCalledWith('cookie-config-hash', 'standalone123hash', 30);

            // Verify non-required standalone cookie is NOT set
            expect(setItemSpy).not.toHaveBeenCalledWith('ga_tracking', 'GA1.2.123456789', 365);

            // Verify total call count (3: PHPSESSID, cookie-preference, cookie-config-hash)
            expect(setItemSpy).toHaveBeenCalledTimes(3);

            setItemSpy.mockRestore();
            removeItemSpy.mockRestore();
        });

        test('handles empty cookie groups gracefully', async () => {
            const mockApiResponse = {
                hash: 'empty123hash',
                elements: [
                    {
                        technicalName: 'minimal-required',
                        isRequired: true,
                        entries: [
                            {
                                cookie: 'cookie-preference',
                                value: '1',
                                expiration: 30
                            },
                            {
                                cookie: 'cookie-config-hash',
                                value: 'empty123hash',
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
            const closeOffCanvasSpy = jest.spyOn(plugin, 'closeOffCanvas');
            const handleUpdateListenerSpy = jest.spyOn(plugin, '_handleUpdateListener');

            const event = { preventDefault: jest.fn() };

            await plugin._handlePermission(event);

            // Verify preference cookies are set by shared function
            expect(setItemSpy).toHaveBeenCalledWith('cookie-preference', '1', 30);
            expect(setItemSpy).toHaveBeenCalledWith('cookie-config-hash', 'empty123hash', 30);

            // Verify total call count (2: cookie-preference, cookie-config-hash from entries)
            expect(setItemSpy).toHaveBeenCalledTimes(2);

            // PHP-managed cookies + cookies we set are considered active
            expect(handleUpdateListenerSpy).toHaveBeenCalledWith(['session-', 'timezone', 'cookie-preference', 'cookie-config-hash'], []);
            expect(closeOffCanvasSpy).toHaveBeenCalled();

            setItemSpy.mockRestore();
        });

        test('handles API errors gracefully', async () => {
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();
            const setItemSpy = jest.spyOn(CookieStorage, 'setItem');

            // Mock fetch to reject
            plugin._fetchCookieGroups = jest.fn().mockResolvedValue(null);

            const event = { preventDefault: jest.fn() };
            await plugin._handlePermission(event);

            expect(plugin._fetchCookieGroups).toHaveBeenCalled();
            // Add assertion that other functions were not called after early return
            expect(setItemSpy).not.toHaveBeenCalled();

            consoleErrorSpy.mockRestore();
            setItemSpy.mockRestore();
        });
    });

    describe('Additional Plugin Methods', () => {
        test('Google reCAPTCHA plugins are initialized when registerGoogleReCaptchaPlugins function exists', () => {
            // Test uncovered lines 285-286
            const mockRegisterFunction = jest.fn();
            window.registerGoogleReCaptchaPlugins = mockRegisterFunction;
            const initializePluginsSpy = jest.spyOn(PluginManager, 'initializePlugins');

            plugin._handleUpdateListener(['test-cookie'], []);

            expect(mockRegisterFunction).toHaveBeenCalled();
            expect(initializePluginsSpy).toHaveBeenCalled();

            delete window.registerGoogleReCaptchaPlugins;
            initializePluginsSpy.mockRestore();
        });

        test('OffCanvas close listener is properly registered and unsubscribes', () => {
            // Test uncovered lines 383-384
            const subscribeSpy = jest.spyOn(document.$emitter, 'subscribe');
            const unsubscribeSpy = jest.spyOn(document.$emitter, 'unsubscribe');
            const checkAndShowCookieBarSpy = jest.spyOn(plugin, '_checkAndShowCookieBarIfNeeded');

            plugin._registerOffCanvasCloseListener();

            // Get the callback that was subscribed
            const callback = subscribeSpy.mock.calls[0][1];

            // Simulate the offcanvas close event
            callback();

            expect(checkAndShowCookieBarSpy).toHaveBeenCalled();
            expect(unsubscribeSpy).toHaveBeenCalledWith('onCloseOffcanvas', callback);

            subscribeSpy.mockRestore();
            unsubscribeSpy.mockRestore();
            checkAndShowCookieBarSpy.mockRestore();
        });

        test('_getOffCanvas behavior with and without elements', () => {
            const originalGetOffCanvas = OffCanvas.getOffCanvas;

            // Test with element available
            const mockElement = { test: 'element' };
            OffCanvas.getOffCanvas = jest.fn(() => [mockElement]);
            expect(plugin._getOffCanvas()).toBe(mockElement);

            // Test with no elements available
            OffCanvas.getOffCanvas = jest.fn(() => []);
            expect(plugin._getOffCanvas()).toBe(false);

            // Restore original function
            OffCanvas.getOffCanvas = originalGetOffCanvas;
        });

        test('_findParentEl finds parent or returns null correctly', () => {
            // Test finding correct parent element
            const grandparent = document.createElement('div');
            grandparent.className = 'grandparent';

            const parent = document.createElement('div');
            parent.className = 'parent test-class';
            grandparent.appendChild(parent);

            const child = document.createElement('div');
            child.className = 'child';
            parent.appendChild(child);

            document.body.appendChild(grandparent);

            // Should find the parent with target class
            expect(plugin._findParentEl(child, 'test-class')).toBe(parent);

            // Should return null when class not found
            expect(plugin._findParentEl(child, 'non-existent-class')).toBe(null);

            document.body.removeChild(grandparent);
        });

        test('checkbox utility functions work correctly', () => {
            // Test _isChecked
            const checkedInput = document.createElement('input');
            checkedInput.type = 'checkbox';
            checkedInput.checked = true;

            const uncheckedInput = document.createElement('input');
            uncheckedInput.type = 'checkbox';
            uncheckedInput.checked = false;

            expect(plugin._isChecked(checkedInput)).toBe(true);
            expect(plugin._isChecked(uncheckedInput)).toBe(false);

            // Test _toggleWholeGroup
            const group = document.createElement('div');
            const checkbox1 = document.createElement('input');
            checkbox1.type = 'checkbox';
            checkbox1.checked = false;
            group.appendChild(checkbox1);

            const checkbox2 = document.createElement('input');
            checkbox2.type = 'checkbox';
            checkbox2.checked = false;
            group.appendChild(checkbox2);

            // Toggle to true
            plugin._toggleWholeGroup(true, group);
            expect(checkbox1.checked).toBe(true);
            expect(checkbox2.checked).toBe(true);

            // Toggle to false
            plugin._toggleWholeGroup(false, group);
            expect(checkbox1.checked).toBe(false);
            expect(checkbox2.checked).toBe(false);
        });

        test('_getCookies with "default" type returns empty array', () => {
            const result = plugin._getCookies('default');
            expect(result).toEqual([]);
        });

        test('acceptAllCookies fetches cookie groups and sets all cookies', async () => {
            const mockResponse = {
                hash: 'test-hash-456',
                elements: [
                    {
                        isRequired: true,
                        entries: [
                            {
                                cookie: 'cookie-preference',
                                value: '1',
                                expiration: 30,
                            },
                            {
                                cookie: 'cookie-config-hash',
                                value: 'test-hash-456',
                                expiration: 30,
                            },
                        ],
                    },
                    {
                        isRequired: false,
                        entries: [
                            {
                                cookie: 'analytics',
                                value: '1',
                                expiration: 30,
                            },
                            {
                                cookie: 'marketing',
                                value: '1',
                                expiration: 60,
                            },
                        ],
                    },
                ],
            };

            global.fetch = jest.fn().mockResolvedValue({
                json: jest.fn().mockResolvedValue(mockResponse),
            });

            const setItemSpy = jest.spyOn(CookieStorage, 'setItem');

            await plugin.acceptAllCookies(true);

            expect(global.fetch).toHaveBeenCalledWith(window.router['frontend.cookie.groups'], {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            // Check that cookie preference was set from the entries
            expect(setItemSpy).toHaveBeenCalledWith(
                'cookie-preference',
                '1',
                30
            );

            // Check that cookie hash was set from the entries
            expect(setItemSpy).toHaveBeenCalledWith(
                'cookie-config-hash',
                'test-hash-456',
                30
            );

            // Check that analytics cookie was set
            expect(setItemSpy).toHaveBeenCalledWith('analytics', '1', 30);

            // Check that marketing cookie was set
            expect(setItemSpy).toHaveBeenCalledWith('marketing', '1', 60);

            setItemSpy.mockRestore();
            global.fetch.mockRestore();
        });
    });

    describe('Checkbox Event Handling', () => {
        test('_handleCheckbox calls correct callback for parent input', () => {
            const parentCheckboxEventSpy = jest.spyOn(plugin, '_parentCheckboxEvent').mockImplementation();

            const input = document.createElement('input');
            input.classList.add(plugin.options.parentInputClass.replace('.', ''));

            const event = { target: input };

            plugin._handleCheckbox(event);

            expect(parentCheckboxEventSpy).toHaveBeenCalledWith(input);

            parentCheckboxEventSpy.mockRestore();
        });

        test('_handleCheckbox calls correct callback for child input', () => {
            const childCheckboxEventSpy = jest.spyOn(plugin, '_childCheckboxEvent').mockImplementation();

            const input = document.createElement('input');
            // Don't add parent class, so it's treated as child

            const event = { target: input };

            plugin._handleCheckbox(event);

            expect(childCheckboxEventSpy).toHaveBeenCalledWith(input);

            childCheckboxEventSpy.mockRestore();
        });

        test('_parentCheckboxEvent toggles whole group', () => {
            const toggleWholeGroupSpy = jest.spyOn(plugin, '_toggleWholeGroup').mockImplementation();
            const findParentElSpy = jest.spyOn(plugin, '_findParentEl').mockReturnValue(document.createElement('div'));

            const target = document.createElement('input');
            target.type = 'checkbox';
            target.checked = true;

            plugin._parentCheckboxEvent(target);

            expect(findParentElSpy).toHaveBeenCalledWith(target, plugin.options.groupClass);
            expect(toggleWholeGroupSpy).toHaveBeenCalledWith(true, expect.any(HTMLElement));

            toggleWholeGroupSpy.mockRestore();
            findParentElSpy.mockRestore();
        });

        test('_childCheckboxEvent toggles parent checkbox', () => {
            const toggleParentCheckboxSpy = jest.spyOn(plugin, '_toggleParentCheckbox').mockImplementation();
            const findParentElSpy = jest.spyOn(plugin, '_findParentEl').mockReturnValue(document.createElement('div'));

            const target = document.createElement('input');
            target.type = 'checkbox';
            target.checked = false;

            plugin._childCheckboxEvent(target);

            expect(findParentElSpy).toHaveBeenCalledWith(target, plugin.options.groupClass);
            expect(toggleParentCheckboxSpy).toHaveBeenCalledWith(false, expect.any(HTMLElement));

            toggleParentCheckboxSpy.mockRestore();
            findParentElSpy.mockRestore();
        });

        test('_toggleParentCheckbox handles different child checkbox states correctly', () => {
            const createGroup = (childStates) => {
                const group = document.createElement('div');

                const parentCheckbox = document.createElement('input');
                parentCheckbox.type = 'checkbox';
                parentCheckbox.className = plugin.options.parentInputClass.replace('.', '');
                group.appendChild(parentCheckbox);

                childStates.forEach(checked => {
                    const childCheckbox = document.createElement('input');
                    childCheckbox.type = 'checkbox';
                    childCheckbox.checked = checked;
                    group.appendChild(childCheckbox);
                });

                return { group, parentCheckbox };
            };

            // Test with some children checked (indeterminate state)
            const { group: group1, parentCheckbox: parent1 } = createGroup([true, false]);
            plugin._toggleParentCheckbox(true, group1);
            expect(parent1.checked).toBe(true);
            expect(parent1.indeterminate).toBe(true);

            // Test with no children checked
            const { group: group2, parentCheckbox: parent2 } = createGroup([false, false]);
            plugin._toggleParentCheckbox(false, group2);
            expect(parent2.checked).toBe(false);
            expect(parent2.indeterminate).toBe(false);

            // Test with all children checked
            const { group: group3, parentCheckbox: parent3 } = createGroup([true, true]);
            plugin._toggleParentCheckbox(true, group3);
            expect(parent3.checked).toBe(true);
            expect(parent3.indeterminate).toBe(false);
        });
    });

    describe('Event Subscription and Registration', () => {
        test('_registerEvents adds event listeners to DOM elements', () => {
            // Create DOM elements that should have event listeners added
            const configButton = document.createElement('button');
            configButton.className = 'js-cookie-configuration-button';
            configButton.innerHTML = '<button>Configure</button>';
            document.body.appendChild(configButton);

            const permissionButton = document.createElement('button');
            permissionButton.className = 'js-cookie-permission-button';
            document.body.appendChild(permissionButton);

            const customLink = document.createElement('a');
            customLink.href = window.router['frontend.cookie.offcanvas'];
            document.body.appendChild(customLink);

            const acceptAllButton = document.createElement('button');
            acceptAllButton.className = 'js-cookie-accept-all-button';
            document.body.appendChild(acceptAllButton);

            const addEventListenerSpy = jest.spyOn(HTMLElement.prototype, 'addEventListener');

            // Re-register events
            plugin._registerEvents();

            expect(addEventListenerSpy).toHaveBeenCalled();

            // Cleanup
            document.body.removeChild(configButton);
            document.body.removeChild(permissionButton);
            document.body.removeChild(customLink);
            document.body.removeChild(acceptAllButton);
            addEventListenerSpy.mockRestore();
        });

        test('handles CustomEvent vs regular payload in subscription', () => {
            let requestConsentCallback;

            // Mock subscribe to capture the callback
            const subscribeSpy = jest.spyOn(document.$emitter, 'subscribe').mockImplementation((eventName, callback) => {
                if (eventName === 'CookieConfiguration/requestConsent') {
                    requestConsentCallback = callback;
                }
            });

            // Create a new instance to trigger init() and our mocked subscribe
            const newPlugin = new CookieConfiguration(document.createElement('div'));
            const openSpy = jest.spyOn(newPlugin, 'openRequestConsentOffCanvas').mockImplementation();

            // Test CustomEvent payload (lines 75-78 coverage)
            const customEvent = new CustomEvent('CookieConfiguration/requestConsent', {
                detail: {
                    route: '/custom-route',
                    cookieName: 'custom-cookie',
                },
            });
            requestConsentCallback.call(newPlugin, customEvent);
            expect(openSpy).toHaveBeenCalledWith('/custom-route', 'custom-cookie');

            // Test regular object payload
            const regularPayload = {
                route: '/regular-route',
                cookieName: 'regular-cookie',
            };

            // Simulate handler with regular payload (no instanceof check)
            requestConsentCallback.call(newPlugin, regularPayload);
            expect(openSpy).toHaveBeenCalledWith('/regular-route', 'regular-cookie');

            subscribeSpy.mockRestore();
            openSpy.mockRestore();
        });

        test('_registerEvents processes custom links correctly', () => {
            // Test line 98 coverage - custom link event registration
            const customLink = document.createElement('a');
            customLink.href = window.router['frontend.cookie.offcanvas'];
            document.body.appendChild(customLink);

            // Mock Array.from and document.querySelectorAll to ensure our link is found
            const originalQuerySelectorAll = document.querySelectorAll;
            document.querySelectorAll = jest.fn((selector) => {
                if (selector === plugin.options.customLinkSelector) {
                    return [customLink];
                }
                return originalQuerySelectorAll.call(document, selector);
            });

            const addEventListenerSpy = jest.spyOn(customLink, 'addEventListener');

            // Re-register events to trigger the line
            plugin._registerEvents();

            expect(addEventListenerSpy).toHaveBeenCalledWith('click', expect.any(Function));

            // Restore
            document.querySelectorAll = originalQuerySelectorAll;
            document.body.removeChild(customLink);
            addEventListenerSpy.mockRestore();
        });
    });

    describe('OffCanvas and UI Interactions', () => {
        test('closeOffCanvas calls callback when provided', () => {
            const callback = jest.fn();

            plugin.closeOffCanvas(callback);

            expect(callback).toHaveBeenCalled();
        });

        test('openRequestConsentOffCanvas callback with offcanvas element', () => {
            const registerEventsSpy = jest.spyOn(plugin, '_registerConsentOffcanvasEvents');
            const openSpy = jest.spyOn(AjaxOffCanvas, 'open').mockImplementation((_route, _reload, callback) => {
                callback();
            });

            plugin.openRequestConsentOffCanvas('/test-route', 'test-cookie');

            const offcanvas = document.querySelector('.offcanvas');
            expect(registerEventsSpy).toHaveBeenCalledWith(offcanvas, 'test-cookie');

            openSpy.mockRestore();
            registerEventsSpy.mockRestore();
        });

        test('openRequestConsentOffCanvas handles missing parameters', () => {
            const openSpy = jest.spyOn(AjaxOffCanvas, 'open');

            // Test with missing route (line 369 coverage)
            plugin.openRequestConsentOffCanvas(null, 'test-cookie');
            expect(openSpy).not.toHaveBeenCalled();

            // Test with missing cookieName
            plugin.openRequestConsentOffCanvas('/test-route', null);
            expect(openSpy).not.toHaveBeenCalled();

            openSpy.mockRestore();
        });

        test('_registerConsentOffcanvasEvents registers all button events', () => {
            // Create a mock offcanvas with all possible buttons
            const mockOffcanvas = document.createElement('div');

            const acceptBtn = document.createElement('button');
            acceptBtn.className = 'js-wishlist-cookie-accept';
            mockOffcanvas.appendChild(acceptBtn);

            const loginBtn = document.createElement('button');
            loginBtn.className = 'js-wishlist-login';
            mockOffcanvas.appendChild(loginBtn);

            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'js-wishlist-cookie-offcanvas-cancel';
            mockOffcanvas.appendChild(cancelBtn);

            const prefBtn = document.createElement('button');
            prefBtn.className = 'js-wishlist-cookie-preferences';
            mockOffcanvas.appendChild(prefBtn);

            const addEventListenerSpy = jest.spyOn(HTMLElement.prototype, 'addEventListener');

            plugin._registerConsentOffcanvasEvents(mockOffcanvas, 'test-cookie');

            // Should have added 4 event listeners (lines 398-416 coverage)
            expect(addEventListenerSpy).toHaveBeenCalledTimes(4);

            addEventListenerSpy.mockRestore();
        });

        test('_onPreferences with offcanvas element present', () => {
            const preventDefault = jest.fn();
            const event = { preventDefault };

            // Mock querySelector to return an element in the callback
            const mockOffcanvasElement = {
                addEventListener: jest.fn()
            };

            const originalQuerySelector = document.querySelector;
            document.querySelector = jest.fn((selector) => {
                if (selector === '.offcanvas') {
                    return mockOffcanvasElement;
                }
                return originalQuerySelector.call(document, selector);
            });

            const openOffCanvasSpy = jest.spyOn(plugin, 'openOffCanvas').mockImplementation((callback) => {
                // Simulate callback execution which would run querySelector
                if (callback) callback();
            });

            plugin._onPreferences(event);

            expect(preventDefault).toHaveBeenCalled();
            expect(AjaxOffCanvas.close).toHaveBeenCalled();
            expect(openOffCanvasSpy).toHaveBeenCalled();
            expect(mockOffcanvasElement.addEventListener).toHaveBeenCalledWith('hidden.bs.offcanvas', expect.any(Function), { once: true });

            // Restore
            document.querySelector = originalQuerySelector;
            openOffCanvasSpy.mockRestore();
        });

        test('openRequestConsentOffCanvas callback with no offcanvas element', () => {
            // Test lines 375-380 coverage - callback when offcanvas not found
            const originalQuerySelector = document.querySelector;
            document.querySelector = jest.fn(() => null); // No offcanvas found

            const registerEventsSpy = jest.spyOn(plugin, '_registerConsentOffcanvasEvents');
            const openSpy = jest.spyOn(AjaxOffCanvas, 'open').mockImplementation((_route, _reload, callback) => {
                // Simulate the callback execution
                callback();
            });

            plugin.openRequestConsentOffCanvas('/test-route', 'test-cookie');

            // Should not register events when no offcanvas is found
            expect(registerEventsSpy).not.toHaveBeenCalled();

            // Restore
            document.querySelector = originalQuerySelector;
            openSpy.mockRestore();
            registerEventsSpy.mockRestore();
        });

        test('_onPreferences with no offcanvas element present', () => {
            // Test line 765 coverage - return when no offcanvas element
            const preventDefault = jest.fn();
            const event = { preventDefault };

            const originalQuerySelector = document.querySelector;
            document.querySelector = jest.fn(() => null); // No offcanvas found

            const openOffCanvasSpy = jest.spyOn(plugin, 'openOffCanvas').mockImplementation((callback) => {
                // Simulate callback execution
                if (callback) callback();
            });

            plugin._onPreferences(event);

            expect(preventDefault).toHaveBeenCalled();
            expect(AjaxOffCanvas.close).toHaveBeenCalled();
            expect(openOffCanvasSpy).toHaveBeenCalled();

            // Restore
            document.querySelector = originalQuerySelector;
            openOffCanvasSpy.mockRestore();
        });
    });

    describe('Cookie Handling Edge Cases', () => {
        test('_handleSubmit with cookies without values', async () => {
            // Test that cookies without values are not set
            const mockResponse = {
                hash: 'test-hash',
                elements: [
                    {
                        isRequired: true,
                        entries: [
                            { cookie: 'cookie-preference', value: '1', expiration: 30 },
                        ],
                    },
                    {
                        isRequired: false,
                        entries: [
                            { cookie: 'valid-cookie', value: '1', expiration: 30 },
                            { cookie: 'invalid-cookie', value: null, expiration: 30 }, // No value
                            { cookie: 'empty-cookie', value: '', expiration: 30 }, // Empty value
                        ],
                    },
                ],
            };

            global.fetch = jest.fn().mockResolvedValue({
                json: jest.fn().mockResolvedValue(mockResponse),
            });

            // Find the offcanvas element and add test checkboxes
            const offcanvas = plugin._getOffCanvas();
            const cookieList = offcanvas.querySelector('.offcanvas-cookie-list');

            // Create a test cookie group
            const testGroup = document.createElement('div');
            testGroup.className = 'offcanvas-cookie-group';
            testGroup.innerHTML = `
                <div class="offcanvas-cookie-entries">
                    <div class="offcanvas-cookie-entry custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="cookie_valid-cookie" checked="checked" data-cookie="valid-cookie" data-cookie-value="1" data-cookie-expiration="30">
                    </div>
                    <div class="offcanvas-cookie-entry custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="cookie_invalid-cookie" checked="checked" data-cookie="invalid-cookie">
                    </div>
                    <div class="offcanvas-cookie-entry custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="cookie_empty-cookie" checked="checked" data-cookie="empty-cookie" data-cookie-value="">
                    </div>
                </div>
            `;
            cookieList.appendChild(testGroup);

            const setItemSpy = jest.spyOn(CookieStorage, 'setItem');

            await plugin._handleSubmit();

            // Should only set valid cookies with values (invalid and empty should not be set due to missing/empty values)
            expect(setItemSpy).toHaveBeenCalledWith('valid-cookie', '1', 30);
            expect(setItemSpy).not.toHaveBeenCalledWith('invalid-cookie', null, 30);
            expect(setItemSpy).not.toHaveBeenCalledWith('empty-cookie', '', 30);

            // Cleanup
            cookieList.removeChild(testGroup);
            setItemSpy.mockRestore();
            global.fetch.mockRestore();
        });

        test('acceptAllCookies methods work correctly with different contexts', async () => {
            const mockResponse = {
                hash: 'test-hash-789',
                elements: [
                    {
                        isRequired: false,
                        entries: [
                            {
                                cookie: 'analytics',
                                value: '1',
                                expiration: 30,
                            },
                        ],
                    },
                ],
            };

            global.fetch = jest.fn().mockResolvedValue({
                json: jest.fn().mockResolvedValue(mockResponse),
            });

            const closeOffCanvasSpy = jest.spyOn(plugin, 'closeOffCanvas').mockImplementation();
            const fetchCookieGroupsSpy = jest.spyOn(plugin, '_fetchCookieGroups');
            const applyCookieConfigurationSpy = jest.spyOn(plugin, '_applyCookieConfiguration');

            // Test acceptAllCookies
            await plugin.acceptAllCookies(false);
            expect(fetchCookieGroupsSpy).toHaveBeenCalled();
            expect(applyCookieConfigurationSpy).toHaveBeenCalledWith(mockResponse.elements, 'all');
            expect(closeOffCanvasSpy).toHaveBeenCalled();

            // Test _acceptAllCookiesFromOffCanvas
            fetchCookieGroupsSpy.mockClear();
            applyCookieConfigurationSpy.mockClear();
            closeOffCanvasSpy.mockClear();

            await plugin._acceptAllCookiesFromOffCanvas();
            expect(fetchCookieGroupsSpy).toHaveBeenCalled();
            expect(applyCookieConfigurationSpy).toHaveBeenCalledWith(mockResponse.elements, 'all');
            expect(closeOffCanvasSpy).toHaveBeenCalled();

            closeOffCanvasSpy.mockRestore();
            fetchCookieGroupsSpy.mockRestore();
            applyCookieConfigurationSpy.mockRestore();
            global.fetch.mockRestore();
        });
    });

    describe('Cookie Groups Fetching', () => {
        test('_fetchCookieGroups makes correct API call and returns data', async () => {
            const mockResponse = {
                hash: 'test-hash',
                elements: [{ cookie: 'test-cookie' }]
            };

            global.fetch = jest.fn().mockResolvedValue({
                json: jest.fn().mockResolvedValue(mockResponse)
            });

            const result = await plugin._fetchCookieGroups();

            expect(global.fetch).toHaveBeenCalledWith(window.router['frontend.cookie.groups'], {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            expect(result).toEqual(mockResponse);

            global.fetch.mockRestore();
        });

        test('_fetchCookieGroups handles errors and returns null', async () => {
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();

            global.fetch = jest.fn().mockRejectedValue(new Error('Network error'));

            const result = await plugin._fetchCookieGroups();

            expect(result).toBeNull();
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to fetch cookie groups:', expect.any(Error));

            global.fetch.mockRestore();
            consoleErrorSpy.mockRestore();
        });
    });

    describe('OffCanvas Close Handling', () => {
        test('_onOffCanvasClose shows cookie bar when user has no preference', () => {
            // Mock no existing preference
            const getItemSpy = jest.spyOn(CookieStorage, 'getItem').mockReturnValue(null);
            const showCookieBarSpy = jest.spyOn(plugin, '_checkAndShowCookieBarIfNeeded').mockImplementation();

            plugin._onOffCanvasClose();

            expect(getItemSpy).toHaveBeenCalledWith(plugin.options.cookiePreference);
            expect(showCookieBarSpy).toHaveBeenCalled();

            getItemSpy.mockRestore();
            showCookieBarSpy.mockRestore();
        });

        test('_onOffCanvasClose does not show cookie bar when user has preference', () => {
            // Mock existing preference
            const getItemSpy = jest.spyOn(CookieStorage, 'getItem').mockReturnValue('1');
            const showCookieBarSpy = jest.spyOn(plugin, '_checkAndShowCookieBarIfNeeded').mockImplementation();

            plugin._onOffCanvasClose();

            expect(getItemSpy).toHaveBeenCalledWith(plugin.options.cookiePreference);
            expect(showCookieBarSpy).not.toHaveBeenCalled();

            getItemSpy.mockRestore();
            showCookieBarSpy.mockRestore();
        });
    });

    describe('Cookie expiration configuration', () => {
        test('uses default expiration from options', () => {
            const plugin = new CookieConfiguration(document.body);
            expect(plugin._getDefaultCookieExpiration()).toBe(30);
        });

        test('allows overriding default expiration via options', () => {
            const plugin = new CookieConfiguration(document.body, { defaultCookieExpiration: 60 });
            expect(plugin._getDefaultCookieExpiration()).toBe(60);
        });

        test('falls back to 30 for invalid expiration values', () => {
            const plugin1 = new CookieConfiguration(document.body, { defaultCookieExpiration: 'invalid' });
            const plugin2 = new CookieConfiguration(document.body, { defaultCookieExpiration: -5 });
            const plugin3 = new CookieConfiguration(document.body, { defaultCookieExpiration: 0 });
            const plugin4 = new CookieConfiguration(document.body, { defaultCookieExpiration: 3.14 });

            expect(plugin1._getDefaultCookieExpiration()).toBe(30);
            expect(plugin2._getDefaultCookieExpiration()).toBe(30);
            expect(plugin3._getDefaultCookieExpiration()).toBe(30);
            expect(plugin4._getDefaultCookieExpiration()).toBe(30);
        });

        test('accepts valid numeric strings', () => {
            const plugin = new CookieConfiguration(document.body, { defaultCookieExpiration: '90' });
            expect(plugin._getDefaultCookieExpiration()).toBe(90);
        });
    });
});
