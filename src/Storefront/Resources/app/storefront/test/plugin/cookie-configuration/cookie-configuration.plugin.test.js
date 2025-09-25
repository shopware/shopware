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

    test('_handlePermission sets cookie preference and calls closeOffCanvas', () => {
        const event = { preventDefault: jest.fn() };
        const setItemSpy = jest.spyOn(CookieStorage, 'setItem');
        const closeOffCanvasSpy = jest.spyOn(plugin, 'closeOffCanvas');

        plugin._handlePermission(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(setItemSpy).toHaveBeenCalledWith('cookie-preference', '1', '30');
        expect(closeOffCanvasSpy).toHaveBeenCalled();

        setItemSpy.mockRestore();
        closeOffCanvasSpy.mockRestore();
    });

    test('closeOffCanvas executes callback when provided', () => {
        const callback = jest.fn();
        plugin.closeOffCanvas(callback);
        expect(AjaxOffCanvas.close).toHaveBeenCalled();
        expect(callback).toHaveBeenCalled();
    });

    test('_handleUpdateListener calls Google reCAPTCHA plugins when available', () => {
        window.registerGoogleReCaptchaPlugins = jest.fn();
        const initializePluginsSpy = jest.spyOn(window.PluginManager, 'initializePlugins');

        plugin._handleUpdateListener([], []);

        expect(window.registerGoogleReCaptchaPlugins).toHaveBeenCalled();
        expect(initializePluginsSpy).toHaveBeenCalled();

        delete window.registerGoogleReCaptchaPlugins;
    });

    test('_checkAndShowCookieBarIfNeeded shows cookie bar when no preference is set', () => {
        const mockCookiePermissionPlugin = {
            _showCookieBar: jest.fn(),
            _setBodyPadding: jest.fn()
        };

        window.PluginManager.getPluginInstances.mockReturnValue([mockCookiePermissionPlugin]);
        jest.spyOn(CookieStorage, 'getItem').mockReturnValue(null);

        plugin._checkAndShowCookieBarIfNeeded();

        expect(mockCookiePermissionPlugin._showCookieBar).toHaveBeenCalled();
        expect(mockCookiePermissionPlugin._setBodyPadding).toHaveBeenCalled();

        CookieStorage.getItem.mockRestore();
    });

    test('_handleCheckbox calls correct callback based on parent input class', () => {
        // Create a group container first
        const group = document.createElement('div');
        group.classList.add('offcanvas-cookie-group');

        const target = document.createElement('input');
        target.type = 'checkbox';
        target.classList.add('offcanvas-cookie-parent-input');

        group.appendChild(target);
        document.body.appendChild(group);

        const parentCheckboxEventSpy = jest.spyOn(plugin, '_parentCheckboxEvent');
        const event = { target };

        plugin._handleCheckbox(event);

        expect(parentCheckboxEventSpy).toHaveBeenCalledWith(target);

        document.body.removeChild(group);
        parentCheckboxEventSpy.mockRestore();
    });

    test('_parentCheckboxEvent toggles whole group', () => {
        // Create a parent checkbox within a group
        const group = document.createElement('div');
        group.classList.add('offcanvas-cookie-group');

        const parentCheckbox = document.createElement('input');
        parentCheckbox.type = 'checkbox';
        parentCheckbox.checked = true;
        parentCheckbox.classList.add('offcanvas-cookie-parent-input');

        group.appendChild(parentCheckbox);
        document.body.appendChild(group);

        const toggleWholeGroupSpy = jest.spyOn(plugin, '_toggleWholeGroup');

        plugin._parentCheckboxEvent(parentCheckbox);

        expect(toggleWholeGroupSpy).toHaveBeenCalledWith(true, group);

        document.body.removeChild(group);
        toggleWholeGroupSpy.mockRestore();
    });

    test('_toggleWholeGroup sets all checkboxes to specified state', () => {
        const group = document.createElement('div');
        const checkbox1 = document.createElement('input');
        checkbox1.type = 'checkbox';
        const checkbox2 = document.createElement('input');
        checkbox2.type = 'checkbox';

        group.appendChild(checkbox1);
        group.appendChild(checkbox2);

        plugin._toggleWholeGroup(true, group);

        expect(checkbox1.checked).toBe(true);
        expect(checkbox2.checked).toBe(true);

        plugin._toggleWholeGroup(false, group);

        expect(checkbox1.checked).toBe(false);
        expect(checkbox2.checked).toBe(false);
    });

    test('CookieConfiguration/requestConsent event subscription is set up during init', () => {
        const subscribeSpy = jest.spyOn(document.$emitter, 'subscribe');

        // Create a fresh plugin instance to test the event subscription in init
        const container = document.createElement('div');
        new CookieConfiguration(container);

        expect(subscribeSpy).toHaveBeenCalledWith('CookieConfiguration/requestConsent', expect.any(Function));

        subscribeSpy.mockRestore();
    });


    test('_registerOffCanvasCloseListener subscribes to onCloseOffcanvas event', () => {
        const subscribeSpy = jest.spyOn(document.$emitter, 'subscribe');

        plugin._registerOffCanvasCloseListener();

        expect(subscribeSpy).toHaveBeenCalledWith('onCloseOffcanvas', expect.any(Function));

        subscribeSpy.mockRestore();
    });

    test('openRequestConsentOffCanvas returns early when route or cookieName is missing', () => {
        const openSpy = jest.spyOn(AjaxOffCanvas, 'open');

        // Test with missing route
        plugin.openRequestConsentOffCanvas('', 'cookieName');
        expect(openSpy).not.toHaveBeenCalled();

        // Test with missing cookieName
        plugin.openRequestConsentOffCanvas('/route', '');
        expect(openSpy).not.toHaveBeenCalled();

        openSpy.mockRestore();
    });

    test('_registerConsentOffcanvasEvents registers all consent button event listeners', () => {
        const offcanvas = document.createElement('div');

        const acceptBtn = document.createElement('button');
        acceptBtn.classList.add('js-wishlist-cookie-accept');

        const loginBtn = document.createElement('button');
        loginBtn.classList.add('js-wishlist-login');

        const cancelBtn = document.createElement('button');
        cancelBtn.classList.add('js-wishlist-cookie-offcanvas-cancel');

        const prefBtn = document.createElement('button');
        prefBtn.classList.add('js-wishlist-cookie-preferences');

        offcanvas.appendChild(acceptBtn);
        offcanvas.appendChild(loginBtn);
        offcanvas.appendChild(cancelBtn);
        offcanvas.appendChild(prefBtn);

        const acceptAddEventListenerSpy = jest.spyOn(acceptBtn, 'addEventListener');
        const loginAddEventListenerSpy = jest.spyOn(loginBtn, 'addEventListener');
        const cancelAddEventListenerSpy = jest.spyOn(cancelBtn, 'addEventListener');
        const prefAddEventListenerSpy = jest.spyOn(prefBtn, 'addEventListener');

        plugin._registerConsentOffcanvasEvents(offcanvas, 'test-cookie');

        expect(acceptAddEventListenerSpy).toHaveBeenCalledWith('click', expect.any(Function));
        expect(loginAddEventListenerSpy).toHaveBeenCalledWith('click', expect.any(Function));
        expect(cancelAddEventListenerSpy).toHaveBeenCalledWith('click', expect.any(Function));
        expect(prefAddEventListenerSpy).toHaveBeenCalledWith('click', expect.any(Function));
    });

    test('acceptAllCookies with loadIntoMemory false calls _handleAcceptAll and closeOffCanvas', () => {
        const handleAcceptAllSpy = jest.spyOn(plugin, '_handleAcceptAll');
        const closeOffCanvasSpy = jest.spyOn(plugin, 'closeOffCanvas');

        plugin.acceptAllCookies(false);

        expect(handleAcceptAllSpy).toHaveBeenCalled();
        expect(closeOffCanvasSpy).toHaveBeenCalled();

        handleAcceptAllSpy.mockRestore();
        closeOffCanvasSpy.mockRestore();
    });

    test('_getCookies returns empty array for default case', () => {
        const result = plugin._getCookies('invalid-type');
        expect(result).toEqual([]);
    });
});
