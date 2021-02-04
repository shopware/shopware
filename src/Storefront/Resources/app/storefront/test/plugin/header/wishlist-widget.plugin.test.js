/**
 * @jest-environment jsdom
 */

/* eslint-disable */
import WishlistWidgetPlugin from 'src/plugin/header/wishlist-widget.plugin';
import WishlistLocalStoragePlugin from 'src/plugin/wishlist/local-wishlist.plugin';

describe('WishlistWidgetPlugin tests', () => {
    let wishlistWidgetPlugin = undefined;
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        // create mocks
        window.wishlistEnabled = true;

        // mock search plugin
        const mockElement = document.createElement('div');

        window.PluginManager = {
            getPluginInstancesFromElement: () => {
                return new Map();
            },
            getPluginInstanceFromElement: () => {
                return new WishlistLocalStoragePlugin(mockElement);
            },
            getPluginInstances: () => {
                return new Map();
            },
            getPlugin: () => {
                return {
                    get: () => []
                };
            },
        };

        wishlistWidgetPlugin = new WishlistWidgetPlugin(mockElement);
    });

    afterEach(() => {
        wishlistWidgetPlugin = undefined;
        spyInitializePlugins.mockClear();
    });

    test('Wishlist widget plugin exists', () => {
        expect(typeof wishlistWidgetPlugin).toBe('object');
    });

    test('Wishlist widget subscriber should be called when events are emitted', () => {
        wishlistWidgetPlugin._wishlistStorage.getCurrentCounter = jest.fn();
        wishlistWidgetPlugin._wishlistStorage.$emitter.publish('Wishlist/onProductsLoaded');
        expect(wishlistWidgetPlugin._wishlistStorage.getCurrentCounter).toHaveBeenCalled();

        wishlistWidgetPlugin._reInitWishlistButton = jest.fn();
        wishlistWidgetPlugin._wishlistStorage.getCurrentCounter = jest.fn();
        wishlistWidgetPlugin._wishlistStorage.$emitter.publish('Wishlist/onProductAdded', {
            productId: 'product-01'
        });
        expect(wishlistWidgetPlugin._wishlistStorage.getCurrentCounter).toHaveBeenCalled();
        expect(wishlistWidgetPlugin._reInitWishlistButton).toHaveBeenCalled();

        wishlistWidgetPlugin._reInitWishlistButton = jest.fn();
        wishlistWidgetPlugin._wishlistStorage.getCurrentCounter = jest.fn();
        wishlistWidgetPlugin._wishlistStorage.$emitter.publish('Wishlist/onProductRemoved', {
            productId: 'product-01'
        });
        expect(wishlistWidgetPlugin._wishlistStorage.getCurrentCounter).toHaveBeenCalled();
        expect(wishlistWidgetPlugin._reInitWishlistButton).toHaveBeenCalled();
    });
});


