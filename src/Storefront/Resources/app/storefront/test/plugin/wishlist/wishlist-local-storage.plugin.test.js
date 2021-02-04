/**
 * @jest-environment jsdom
 */

import WishlistLocalStoragePlugin from 'src/plugin/wishlist/local-wishlist.plugin';
import BaseWishlistStoragePlugin from 'src/plugin/wishlist/base-wishlist-storage.plugin';
import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';
import Storage from 'src/helper/storage/storage.helper';

describe('WishlistLocalStoragePlugin tests', () => {
    let wishlistStoragePlugin = undefined;
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        CookieStorageHelper.setItem('wishlist-enabled', true);
        // create mocks
        window.wishlistEnabled = true;

        const mockElement = document.createElement('div');

        window.PluginManager = {
            getPluginInstancesFromElement: () => {
                return new Map();
            },
            getPlugin: () => {
                return {
                    get: () => []
                };
            }
        };

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
        expect(Storage.getItem(key)).toEqual(false);
    });
});


