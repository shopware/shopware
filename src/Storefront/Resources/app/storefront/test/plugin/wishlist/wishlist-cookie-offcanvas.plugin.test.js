import WishlistCookieOffcanvasPlugin from 'src/plugin/wishlist/wishlist-cookie-offcanvas.plugin';
import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';
import AjaxOffCanvas from 'src/plugin/offcanvas/ajax-offcanvas.plugin';

describe('WishlistCookieOffcanvasPlugin tests', () => {
    let origRouter, origLocation, origPluginManager, el, plugin;
    beforeAll(() => {
        origRouter = window.router;
        origLocation = window.location;
        origPluginManager = window.PluginManager;
    });

    afterAll(() => {
        window.router = origRouter;
        window.location = origLocation;
        window.PluginManager = origPluginManager;
    });

    beforeEach(() => {
        // clear cookie
        CookieStorageHelper.removeItem(WishlistCookieOffcanvasPlugin.options.cookieName);

        window.PluginManager = {
            initializePlugins: jest.fn(),
            getPluginInstances: jest.fn(() => []),
            getPluginInstanceFromElement: jest.fn(),
            getPluginInstancesFromElement: jest.fn(() => new Map()),
            getPlugin: jest.fn(() => new Map([
                ['instances', []]
            ]))
        };
        window.router = { 'frontend.account.login.page': '/login' };
        delete window.location;
        window.location = { href: '' };

        jest
            .spyOn(AjaxOffCanvas, 'open')
            .mockImplementation(jest.fn());
        jest
            .spyOn(AjaxOffCanvas, 'close')
            .mockImplementation(jest.fn());

        el = document.createElement('div');
        el.innerHTML = `
            <button class="js-wishlist-cookie-accept"></button>
            <button class="js-wishlist-login"></button>
            <button class="js-wishlist-cookie-offcanvas-cancel"></button>
            <a href="#" class="js-wishlist-cookie-preferences"></a>
        `;
        plugin = new WishlistCookieOffcanvasPlugin(el);
        plugin.$emitter = { publish: jest.fn() };
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    test('plugin instance exists', () => {
        expect(plugin).toBeInstanceOf(WishlistCookieOffcanvasPlugin);
    });

    test('hasConsent returns false when cookie not set', () => {
        expect(WishlistCookieOffcanvasPlugin.hasConsent()).toBe(false);
    });

    test('hasConsent returns true when cookie set', () => {
        CookieStorageHelper.setItem('wishlist-enabled', '1', 30);
        expect(WishlistCookieOffcanvasPlugin.hasConsent()).toBe(true);
    });

    test('_onAccept sets cookie, closes offcanvas, and emits event', () => {
        plugin.options = { cookieName: 'wishlist-enabled', cookieLifetime: 7, productId: 'test-product' };
        plugin._onAccept();

        expect(CookieStorageHelper.getItem('wishlist-enabled')).toBe('1');
        expect(AjaxOffCanvas.close).toHaveBeenCalled();
        expect(plugin.$emitter.publish).toHaveBeenCalledWith(
            'WishlistCookie/onAccept',
            { productId: 'test-product' }
        );
    });


    test('_onLogin closes offcanvas, redirects, and emits event', () => {
        plugin._onLogin();

        expect(AjaxOffCanvas.close).toHaveBeenCalled();
        expect(plugin.$emitter.publish).toHaveBeenCalledWith('Wishlist/onLoginRedirect');
        expect(window.location.href).toBe('/login');
    });

    test('_onCancel just closes offcanvas', () => {
        plugin._onCancel();

        expect(AjaxOffCanvas.close).toHaveBeenCalled();
    });

    test('_onPreferences() does nothing if no CookieConfiguration plugin', () => {
        expect(() => plugin._onPreferences({ preventDefault: jest.fn() })).not.toThrow();
        expect(window.PluginManager.getPluginInstances).toHaveBeenCalledWith('CookieConfiguration');
    });

    test('_onPreferences() opens the CookieConfiguration offcanvas and subscribes to updates', () => {
        const configuration = { openOffCanvas: jest.fn(cb => cb()) };
        window.PluginManager.getPluginInstances.mockReturnValue([configuration]);

        const spySub = jest.fn();
        document.$emitter = { subscribe: spySub };

        plugin.options = { cookieName: 'test-cookie' };
        plugin._onPreferences({ preventDefault: () => {} });

        expect(AjaxOffCanvas.close).toHaveBeenCalled();
        expect(configuration.openOffCanvas).toHaveBeenCalled();
        expect(spySub).toHaveBeenCalledWith(
            'CookieConfiguration_Update',
            expect.any(Function)
        );
    });

    test('requestConsent() opens offcanvas and wires up onConsent callback', () => {
        const onConsent = jest.fn();
        const pluginInstance = {
            options: {},
            $emitter: { subscribe: jest.fn() }
        };

        document.body.innerHTML = `<div class="offcanvas"></div>`;

        window.PluginManager.getPluginInstanceFromElement.mockReturnValue(pluginInstance);

        WishlistCookieOffcanvasPlugin.requestConsent('productId', onConsent);

        // AjaxOffCanvas.open should be called with correct args
        expect(AjaxOffCanvas.open).toHaveBeenCalledWith(
            '/wishlist/cookie-offcanvas',
            false,
            expect.any(Function),
            'left'
        );

        const openCallback = AjaxOffCanvas.open.mock.calls[0][2];
        openCallback();

        // ensure plugins re-initialized and subscription set
        expect(window.PluginManager.initializePlugins).toHaveBeenCalled();
        expect(pluginInstance.options.productId).toBe('productId');
        expect(pluginInstance.$emitter.subscribe).toHaveBeenCalledWith(
            'WishlistCookie/onAccept',
            onConsent
        );
    });

    test('no errors when .offcanvas element is missing', () => {
        document.body.innerHTML = '';
        expect(() => {
            WishlistCookieOffcanvasPlugin.requestConsent('productId', () => {});
        }).not.toThrow();
        expect(AjaxOffCanvas.open).toHaveBeenCalled();
    });
});
