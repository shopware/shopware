/* eslint-disable */
import WishlistLocalStoragePlugin from 'src/plugin/wishlist/local-wishlist.plugin';
import GuestWishlistPagePlugin from 'src/plugin/wishlist/guest-wishlist-page.plugin';
import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';

/**
 * @package checkout
 */
describe('GuestWishlistPagePlugin tests', () => {
    let guestWishlistPagePlugin = undefined;
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        // create mocks
        window.wishlistEnabled = true;

        const mockElement = document.createElement('div');

        window.PluginManager.getPluginInstanceFromElement = () => {
            return new WishlistLocalStoragePlugin(mockElement);
        }

        const wishlistBasket = document.createElement('div');
        wishlistBasket.setAttribute('id', 'wishlist-basket');
        document.body.appendChild(wishlistBasket);

        // Mock the function which should be called on load
        jest.spyOn(GuestWishlistPagePlugin.prototype, '_loadProductListForGuest').mockImplementation(jest.fn());
        guestWishlistPagePlugin = new GuestWishlistPagePlugin(mockElement);
    });

    afterEach(() => {
        guestWishlistPagePlugin = undefined;
        spyInitializePlugins.mockClear();
    });

    test('GuestWishlistPage plugin exists', () => {
        expect(typeof guestWishlistPagePlugin).toBe('object');
    });

    test('_loadProductListForGuest get called on click', () => {
        const _loadProductListForGuestShouldBeCalled = jest.fn();

        // Mock the function which should be called on load
        jest.spyOn(GuestWishlistPagePlugin.prototype, '_loadProductListForGuest').mockImplementation(_loadProductListForGuestShouldBeCalled);

        const mockClickableDomElement = document.createElement('div');
        new GuestWishlistPagePlugin(mockClickableDomElement);

        expect(_loadProductListForGuestShouldBeCalled).toHaveBeenCalled();

        // Reset mock
        GuestWishlistPagePlugin.prototype._loadProductListForGuest.mockRestore();
    });

    test('_cleanInvalidGuestProductIds method test', () => {
        CookieStorageHelper.setItem('wishlist-enabled', true);

        const validProductIds = ['product_1', 'product_2', 'product_3'];

        validProductIds.forEach(productId => {
            guestWishlistPagePlugin._wishlistStorage.add(productId)
        });

        const responseProductIds = ['product_2', 'product_3'];

        const responseProductForms = responseProductIds.map(productId => {
            const form = document.createElement('form');
            form.action = 'shopware.test/' + productId;
            return form;
        })

        const localStorageProductIds = guestWishlistPagePlugin._wishlistStorage.getProducts();

        guestWishlistPagePlugin._cleanInvalidGuestProductIds(Object.keys(localStorageProductIds), responseProductForms);
        const expectNewStorageProducts = ['product_2', 'product_3'];

        expect(Object.keys(guestWishlistPagePlugin._wishlistStorage.getProducts())).toEqual(expectNewStorageProducts);
    });

    test('_getWishlistStorage method test', () => {
        CookieStorageHelper.setItem('wishlist-enabled', true);

        const validProductIds = ['product_1', 'product_2', 'product_3'];

        validProductIds.forEach(productId => {
            guestWishlistPagePlugin._wishlistStorage.add(productId)
        });

        expect(Object.keys(guestWishlistPagePlugin._wishlistStorage.getProducts()).length).toBe(3);

        window.useDefaultCookieConsent = true;

        CookieStorageHelper.removeItem('wishlist-enabled');

        // init guest wishlist page plugin
        guestWishlistPagePlugin._getWishlistStorage();

        expect(Object.keys(guestWishlistPagePlugin._wishlistStorage.getProducts()).length).toBe(0);
    });
});


