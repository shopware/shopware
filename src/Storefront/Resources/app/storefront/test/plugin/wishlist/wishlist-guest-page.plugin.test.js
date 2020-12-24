/**
 * @jest-environment jsdom
 */

/* eslint-disable */
import WishlistLocalStoragePlugin from 'src/plugin/wishlist/local-wishlist.plugin';
import WishlistGuestPagePlugin from 'src/plugin/wishlist/wishlist-guest-page.plugin';
import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';

describe('WishlistGuestPagePlugin tests', () => {
    let wishlistGuestPagePlugin = undefined;
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        // create mocks
        window.wishlistEnabled = true;
        window.csrf = {
            enabled: false
        };
        window.router = [];


        const mockElement = document.createElement('div');

        window.PluginManager = {
            getPluginInstancesFromElement: () => {
                return new Map();
            },
            getPluginInstanceFromElement: () => {
                return new WishlistLocalStoragePlugin(mockElement);
            },
            getPlugin: () => {
                return {
                    get: () => []
                };
            },
        };

        const wishlistBasket = document.createElement('div');
        wishlistBasket.setAttribute('id', 'wishlist-basket');
        document.body.appendChild(wishlistBasket);

        // Mock the function which should be called on load
        jest.spyOn(WishlistGuestPagePlugin.prototype, '_loadProductListForGuest').mockImplementation(jest.fn());
        wishlistGuestPagePlugin = new WishlistGuestPagePlugin(mockElement);
    });

    afterEach(() => {
        wishlistGuestPagePlugin = undefined;
        spyInitializePlugins.mockClear();
    });

    test('WishlistGuestPage plugin exists', () => {
        expect(typeof wishlistGuestPagePlugin).toBe('object');
    });

    test('_loadProductListForGuest get called on click', () => {
        const _loadProductListForGuestShouldBeCalled = jest.fn();

        // Mock the function which should be called on load
        jest.spyOn(WishlistGuestPagePlugin.prototype, '_loadProductListForGuest').mockImplementation(_loadProductListForGuestShouldBeCalled);

        const mockClickableDomElement = document.createElement('div');
        new WishlistGuestPagePlugin(mockClickableDomElement);

        expect(_loadProductListForGuestShouldBeCalled).toHaveBeenCalled();

        // Reset mock
        WishlistGuestPagePlugin.prototype._loadProductListForGuest.mockRestore();
    });

    test('_cleanInvalidGuestProductIds method test', () => {
        CookieStorageHelper.setItem('wishlist-enabled', true);

        const validProductIds = ['product_1', 'product_2', 'product_3'];

        validProductIds.forEach(productId => {
            wishlistGuestPagePlugin._wishlistStorage.add(productId)
        });

        const responseProductIds = ['product_2', 'product_3'];

        const responseProductForms = responseProductIds.map(productId => {
            const form = document.createElement('form');
            form.action = 'shopware.test/' + productId;
            return form;
        })

        const localStorageProductIds = wishlistGuestPagePlugin._wishlistStorage.getProducts();

        wishlistGuestPagePlugin._cleanInvalidGuestProductIds(Object.keys(localStorageProductIds), responseProductForms);
        const expectNewStorageProducts = ['product_2', 'product_3'];

        expect(Object.keys(wishlistGuestPagePlugin._wishlistStorage.getProducts())).toEqual(expectNewStorageProducts);
    });
});


