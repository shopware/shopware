/* eslint-disable */
import BaseWishlistStoragePlugin from 'src/plugin/wishlist/base-wishlist-storage.plugin';

/**
 * @package checkout
 */
describe('BaseWishlistStoragePlugin tests', () => {
    let wishlistStoragePlugin = undefined;
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        // create mocks
        window.wishlistEnabled = true;

        const mockElement = document.createElement('div');

        wishlistStoragePlugin = new BaseWishlistStoragePlugin(mockElement);
    });

    afterEach(() => {
        wishlistStoragePlugin = undefined;
        spyInitializePlugins.mockClear();
    });

    test('BaseWishlistStoragePlugin exists', () => {
        expect(typeof wishlistStoragePlugin).toBe('object');
    });

    test('BaseWishlistStoragePlugin methods test', () => {
        const products = {
            'PRODUCT_1': 'product 1',
            'PRODUCT_2': 'product 2',
            'PRODUCT_3': 'product 3',
        };

        expect(wishlistStoragePlugin.getCurrentCounter()).toEqual(0);
        wishlistStoragePlugin.products = products;

        let loadedEventFired = false;
        let addedProductEventFired = false;
        let removedProductEventFired = false;

        wishlistStoragePlugin.$emitter.subscribe('Wishlist/onProductsLoaded', e => {
            expect(e.detail.products).toEqual(products);
            loadedEventFired = true;
        });

        wishlistStoragePlugin.$emitter.subscribe('Wishlist/onProductRemoved', e => {
            expect(e.detail.products).toEqual(products);
            expect(e.detail.productId).toEqual('PRODUCT_NEW');
            removedProductEventFired = true;
        });

        wishlistStoragePlugin.$emitter.subscribe('Wishlist/onProductAdded', e => {
            expect(e.detail.products).toEqual(products);
            expect(e.detail.productId).toEqual('PRODUCT_NEW');
            addedProductEventFired = true;
        });

        wishlistStoragePlugin.load();

        expect(wishlistStoragePlugin.getCurrentCounter()).toEqual(3);

        expect(wishlistStoragePlugin.has('PRODUCT_1')).toEqual(true);
        expect(wishlistStoragePlugin.has('PRODUCT_NEW')).toEqual(false);

        wishlistStoragePlugin.add('PRODUCT_NEW');
        expect(wishlistStoragePlugin.getCurrentCounter()).toEqual(4);
        expect(wishlistStoragePlugin.has('PRODUCT_NEW')).toEqual(true);

        wishlistStoragePlugin.remove('PRODUCT_NEW');
        expect(wishlistStoragePlugin.has('PRODUCT_NEW')).toEqual(false);
        expect(wishlistStoragePlugin.getCurrentCounter()).toEqual(3);

        expect(loadedEventFired).toEqual(true);
        expect(removedProductEventFired).toEqual(true);
        expect(addedProductEventFired).toEqual(true);

        expect(wishlistStoragePlugin.getProducts()).toEqual(products)
    });
});


