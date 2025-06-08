import WishlistLocalStoragePlugin from 'src/plugin/wishlist/local-wishlist.plugin';
import BaseWishlistStoragePlugin from 'src/plugin/wishlist/base-wishlist-storage.plugin';
import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';
import Storage from 'src/helper/storage/storage.helper';
import NativeEventEmitter from 'src/helper/emitter.helper';

/**
 * @package checkout
 */
describe('WishlistLocalStoragePlugin tests', () => {
    let wishlistStoragePlugin = undefined;
    let spyInitializePlugins = jest.fn();
    const guestLogoutBtn = document.createElement('a')
    guestLogoutBtn.$emitter = new NativeEventEmitter();

    beforeEach(() => {
        CookieStorageHelper.setItem('wishlist-enabled', true);
        // create mocks
        window.wishlistEnabled = true;

        const mockElement = document.createElement('div');

        window.PluginManager.getPluginInstances = () => {
            return [guestLogoutBtn];
        }

        wishlistStoragePlugin = new WishlistLocalStoragePlugin(mockElement);
    });

    afterEach(() => {
        wishlistStoragePlugin = undefined;
        spyInitializePlugins.mockClear();
    });

    test('LocalWishlistStoragePlugin exists', () => {
        expect(typeof wishlistStoragePlugin).toBe('object');
        expect(wishlistStoragePlugin instanceof BaseWishlistStoragePlugin).toBe(true);
    });

    test('LocalWishlistStoragePlugin methods test', () => {
        window.salesChannelId = 'http://shopware.test';
        const key = wishlistStoragePlugin._getStorageKey();
        expect(key).toEqual('wishlist-http://shopware.test');

        Storage.removeItem(key);

        wishlistStoragePlugin.load();

        expect(wishlistStoragePlugin.getCurrentCounter()).toEqual(0);

        wishlistStoragePlugin.add('PRODUCT_001');

        expect(wishlistStoragePlugin.getCurrentCounter()).toEqual(1);
        expect(Object.keys(JSON.parse(Storage.getItem(key)))[0]).toEqual('PRODUCT_001');

        wishlistStoragePlugin.remove('PRODUCT_001');
        expect(wishlistStoragePlugin.getCurrentCounter()).toEqual(0);
        expect(Storage.getItem(key)).toBeFalsy();
    });

    test('LocalWishlistStoragePlugin redirect to login on add product when cookie consent is not given', () => {
        window = Object.create(window);
        Object.defineProperty(window, 'location', {
            value: {
                href: 'http://shopware.test',
            },
            writable: true,
        });

        window.useDefaultCookieConsent = true;
        CookieStorageHelper.removeItem('wishlist-enabled');

        let loginRedirectEventFired = false;

        wishlistStoragePlugin.$emitter.subscribe('Wishlist/onLoginRedirect', () => {
            loginRedirectEventFired = true;
        });

        wishlistStoragePlugin.add('PRODUCT_001', { afterLoginPath: 'http://shopware.test/login' });

        expect(loginRedirectEventFired).toBe(true);
        expect(window.location.href).toBe('http://shopware.test/login');
    });

    test('LocalWishlistStoragePlugin clear wishlist storage on guest logout', () => {
        const key = wishlistStoragePlugin._getStorageKey();

        wishlistStoragePlugin.add('PRODUCT_001');

        guestLogoutBtn.$emitter.publish('guest-logout');

        expect(Storage.getItem(key)).toBeFalsy();
    });
});


