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
        document.body.innerHTML = '<div class="wishlist-widget"></div>';

        const mockElement = document.querySelector('.wishlist-widget');

        window.PluginManager.getPluginInstanceFromElement = () => {
            return new WishlistLocalStoragePlugin(mockElement);
        }

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

    test('Wishlist widget should render counter by default', () => {
        wishlistWidgetPlugin._wishlistStorage.getCurrentCounter = jest.fn().mockReturnValueOnce(5);
        wishlistWidgetPlugin._wishlistStorage.$emitter.publish('Wishlist/onProductsLoaded');

        expect(document.body.querySelector('.wishlist-widget').innerHTML).toBe('5');
    });

    test('Wishlist widget should not render counter when deactivated via option', () => {
        wishlistWidgetPlugin.options.showCounter = false;

        wishlistWidgetPlugin._wishlistStorage.getCurrentCounter = jest.fn().mockReturnValueOnce(5);
        wishlistWidgetPlugin._wishlistStorage.$emitter.publish('Wishlist/onProductsLoaded');

        expect(document.body.querySelector('.wishlist-widget').innerHTML).toBe('');
    });

    test('Wishlist widget will update live-area content on change', () => {
        let expectedCounter = null;
        wishlistWidgetPlugin._wishlistStorage.getCurrentCounter = jest.fn(() => expectedCounter);

        const liveArea = document.createElement('div');
        liveArea.id = 'wishlist-basket-live-area';
        document.body.appendChild(liveArea);

        expectedCounter = 5;
        wishlistWidgetPlugin._wishlistStorage.$emitter.publish('Wishlist/onProductsLoaded');
        expect(liveArea.innerHTML).toBe('<p>5</p>');

        expectedCounter = 10;
        wishlistWidgetPlugin._wishlistStorage.$emitter.publish('Wishlist/onProductsLoaded');
        expect(liveArea.innerHTML).toBe('<p>10</p>');
    });
});


