import WishlistLocalStoragePlugin from 'src/plugin/wishlist/local-wishlist.plugin';
import AddToWishlistPlugin from 'src/plugin/wishlist/add-to-wishlist.plugin';

/**
 * @package checkout
 */
describe('AddToWishlistPlugin tests', () => {
    let addToWishlistPlugin = undefined;
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        // create mocks
        window.wishlistEnabled = true;

        // mock search plugin
        const mockElement = document.createElement('div');

        window.PluginManager.getPluginInstanceFromElement = () => {
            return new WishlistLocalStoragePlugin(mockElement);
        }

        const wishlistBasket = document.createElement('div');
        wishlistBasket.setAttribute('id', 'wishlist-basket');
        document.body.appendChild(wishlistBasket);

        addToWishlistPlugin = new AddToWishlistPlugin(mockElement);
    });

    afterEach(() => {
        addToWishlistPlugin = undefined;
        spyInitializePlugins.mockClear();
    });

    test('Add To Wishlist widget plugin exists', () => {
        expect(typeof addToWishlistPlugin).toBe('object');
    });

    test('_onClick get called on click', () => {
        const shouldBeClicked = jest.fn();

        // Mock the function which should be called on click
        jest.spyOn(AddToWishlistPlugin.prototype, '_onClick').mockImplementation(shouldBeClicked);

        const mockClickableDomElement = document.createElement('div');
        new AddToWishlistPlugin(mockClickableDomElement);

        // simulate click
        mockClickableDomElement.click();

        expect(shouldBeClicked).toHaveBeenCalled();

        // Reset mock
        AddToWishlistPlugin.prototype._onClick.mockRestore();
    });

    test('initStateClasses get called on login redirect event', () => {
        const shouldBeCalled = jest.fn();

        // Mock the function which should be called on login redirect event
        jest.spyOn(AddToWishlistPlugin.prototype, 'initStateClasses').mockImplementation(shouldBeCalled);

        let plugin = new AddToWishlistPlugin(document.createElement('div'));

        // reset counter because initStateClasses is called on init
        shouldBeCalled.mockClear();

        plugin._wishlistStorage.$emitter.publish('Wishlist/onLoginRedirect');

        expect(shouldBeCalled).toHaveBeenCalled();

        // Reset mock
        AddToWishlistPlugin.prototype.initStateClasses.mockRestore();
    });

    test('element state classes is set when initStateClasses get called', () => {
        const mockElement = document.createElement('div');
        mockElement.setAttribute('id', 'add-to-wishlist');
        document.body.appendChild(mockElement);

        let plugin = new AddToWishlistPlugin(mockElement, {
            router: {
                add: {},
                remove: {}
            }
        });

        plugin.initStateClasses();

        expect(document.getElementById('add-to-wishlist').classList.contains('product-wishlist-not-added')).toBe(true);
        expect(document.getElementById('add-to-wishlist').classList.contains('product-wishlist-added')).toBe(false);

        mockElement.click();

        expect(document.getElementById('add-to-wishlist').classList.contains('product-wishlist-loading')).toBe(true);

        // called by WishlistWidgetPlugin
        plugin.initStateClasses();

        expect(document.getElementById('add-to-wishlist').classList.contains('product-wishlist-not-added')).toBe(false);
        expect(document.getElementById('add-to-wishlist').classList.contains('product-wishlist-added')).toBe(true);

        expect(document.getElementById('add-to-wishlist').classList.contains('product-wishlist-loading')).toBe(false);
    });
});


