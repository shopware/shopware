import OffCanvasCartPlugin from 'src/plugin/offcanvas-cart/offcanvas-cart.plugin';

/**
 * @package checkout
 */

let fireRequestSpy;

jest.mock('src/service/http-client.service', () => {

    const offCanvasCartTemplate = `
        <button class="offcanvas-close js-offcanvas-close">Continue shopping</button>
        <div class="offcanvas-body">
            <div class="cart-item cart-item-product js-cart-item">
                <a class="cart-item-label" href="#">Kek product</a>

                <form action="/checkout/line-item/change-quantity/uuid12345">
                    <select name="quantity" class="js-offcanvas-cart-change-quantity">
                        <option value="1" selected="selected">1</option>
                        <option value="2" >2</option>
                    </select>
                </form>
            </div>

            <div class="cart-item cart-item-product js-cart-item">
                <a class="cart-item-label" href="#">Weird product with huge quantity</a>

                <form action="/checkout/line-item/change-quantity/uuid555">
                    <input type="number" name="quantity" class="js-offcanvas-cart-change-quantity-number" min="1" max="150" step="1" value="1">
                </form>
            </div>
        </div>
    `;

    return function () {
        return {
            post: (url, data, callback) => {
                return callback('<div class="offcanvas-body">Content after update</div>');
            },
            get: (url, callback) => {
                return callback(offCanvasCartTemplate);
            },
        };
    };
});

// Mock ES module import of PluginManager
jest.mock('src/plugin-system/plugin.manager', () => ({
    __esModule: true,
    default: {
        getPluginInstances: () => {
            return [];
        },
    },
}));

describe('OffCanvasCartPlugin tests', () => {

    let plugin;

    beforeEach(() => {

        window.router = {
            'frontend.cart.offcanvas': '/checkout/offcanvas',
        };

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
        };

        document.body.innerHTML = '<div class="header-cart"><a class="header-cart-btn">€ 0,00</a></div>';

        window.PluginManager = {
            initializePlugins: jest.fn(),

            getPluginInstancesFromElement: () => {
                return new Map();
            },

            getPlugin: () => {
                return {
                    get: () => [],
                };
            },

            getPluginInstances: () => {
                return [];
            },
        };

        const el = document.querySelector('.header-cart');

        fireRequestSpy = jest.spyOn(OffCanvasCartPlugin.prototype, '_fireRequest');

        plugin = new OffCanvasCartPlugin(el);
        plugin.$emitter.publish = jest.fn();

        jest.useFakeTimers();
    });

    afterEach(() => {
        fireRequestSpy.mockClear();
    });

    test('creates plugin instance', () => {
        expect(typeof plugin).toBe('object');
    });

    test('open offcanvas cart', () => {
        const el = document.querySelector('.header-cart');

        // Open offcanvas cart with click
        el.dispatchEvent(new Event('click', { bubbles: true }));

        expect(plugin.$emitter.publish).toBeCalledWith('offCanvasOpened', { response: expect.any(String) });
        expect(document.querySelector('.offcanvas.cart-offcanvas')).toBeTruthy();
        expect(document.querySelector('.cart-item-product')).toBeTruthy();
    });

    test('change product quantity using select', () => {
        const el = document.querySelector('.header-cart');

        // Open offcanvas cart with click
        el.dispatchEvent(new Event('click', { bubbles: true }));

        const quantitySelect = document.querySelector('.js-offcanvas-cart-change-quantity');

        // Edit quantity using change event
        quantitySelect.dispatchEvent(new Event('change', { bubbles: true }));

        expect(plugin.$emitter.publish).toBeCalledWith('beforeFireRequest');
        expect(fireRequestSpy).toHaveBeenCalledTimes(1);

        // Verify updated content after quantity change
        expect(document.querySelector('.offcanvas-body').textContent).toBe('Content after update');
    });

    test('change product quantity using number input', () => {
        const el = document.querySelector('.header-cart');

        // Open offcanvas cart with click
        el.dispatchEvent(new Event('click', {
            bubbles: true,
        }));

        const quantityInput = document.querySelector('.js-offcanvas-cart-change-quantity-number');

        // Edit quantity using number input
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));

        // Wait for debounce with time from defaults
        jest.advanceTimersByTime(800);

        expect(plugin.$emitter.publish).toBeCalledWith('beforeFireRequest');
        expect(fireRequestSpy).toHaveBeenCalledTimes(1);

        // Verify updated content after quantity change
        expect(document.querySelector('.offcanvas-body').textContent).toBe('Content after update');
    });

    test('change product quantity should not send too many requests when spamming the number input', () => {
        const el = document.querySelector('.header-cart');

        // Open offcanvas cart with click
        el.dispatchEvent(new Event('click', {
            bubbles: true,
        }));

        const quantityInput = document.querySelector('.js-offcanvas-cart-change-quantity-number');

        // Changing quantity 3 times directly behind each other to simulate spamming the input
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));

        // Wait for debounce with time from defaults
        jest.advanceTimersByTime(800);

        // Change quantity again, this time after waiting long enough
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));

        // Wait for debounce with time from defaults
        jest.advanceTimersByTime(800);

        expect(plugin.$emitter.publish).toBeCalledWith('beforeFireRequest');

        // Only 2 requests should be fired because the throttling should prevent the first spam inputs
        expect(fireRequestSpy).toHaveBeenCalledTimes(2);
    });
});
