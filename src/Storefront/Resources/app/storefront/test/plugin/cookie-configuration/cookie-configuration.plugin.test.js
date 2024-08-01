import CookieStorage from 'src/helper/storage/cookie-storage.helper';
import CookieConfiguration, { COOKIE_CONFIGURATION_UPDATE } from 'src/plugin/cookie/cookie-configuration.plugin';

// Todo: NEXT-23270 - Remove mock ES module import of PluginManager
jest.mock('src/plugin-system/plugin.manager', () => ({
    __esModule: true,
    default: {},
}));

jest.mock('src/service/http-client.service', () => {
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

    return function () {
        return {
            get: (url, callback) => {
                return callback(template);
            },
        };
    };
});

describe('CookieConfiguration plugin tests', () => {
    let plugin;

    beforeEach(() => {
        window.router = {
            'frontend.cookie.offcanvas': 'https://shop.example.com/offcanvas',
        };

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
        };

        window.PluginManager.initializePlugins = () => jest.fn();

        const container = document.createElement('div');
        plugin = new CookieConfiguration(container);

        plugin.openOffCanvas(() => {});
    });

    afterEach(() => {
        const cookies = plugin._getCookies('all');

        cookies.forEach(el => CookieStorage.removeItem(el.cookie));
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

    test('The preference flag is set, when cookie settings are submitted', () => {
        expect(CookieStorage.getItem(plugin.options.cookiePreference)).toBeFalsy();

        plugin._handleSubmit();

        expect(CookieStorage.getItem(plugin.options.cookiePreference)).toBeTruthy();
    });


    test('The preference flag is set, when all cookies are accepted', () => {
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
        const jestFn = jest.fn()
        plugin._httpClient.get = jestFn;

        plugin._acceptAllCookiesFromCookieBar();

        expect(jestFn).toHaveBeenCalledWith('https://shop.example.com/offcanvas', expect.any(Function));
    });
});
