import OffCanvasCartPlugin from 'src/plugin/offcanvas-cart/offcanvas-cart.plugin';
import FocusHandler from 'src/helper/focus-handler.helper';

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
                    <select name="quantity" class="js-offcanvas-cart-change-quantity" data-focus-id="line-item-offcanvas-quantity-uuid12345">
                        <option value="1" selected="selected">1</option>
                        <option value="2" >2</option>
                    </select>
                </form>
            </div>

            <div class="cart-item cart-item-product js-cart-item">
                <a class="cart-item-label" href="#">Weird product with huge quantity</a>

                <form action="/checkout/line-item/change-quantity/uuid555">
                    <input type="number" name="quantity" class="js-offcanvas-cart-change-quantity-number" min="1" max="150" step="1" value="1" data-focus-id="line-item-offcanvas-quantity-uuid555">
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

    function createPlugin(options) {
        document.body.innerHTML = '<div class="header-cart" data-off-canvas-cart="true"><a class="header-cart-btn">€ 0,00</a></div>';

        const el = document.querySelector('.header-cart');

        if (options) {
            el.setAttribute('data-off-canvas-cart-options', JSON.stringify(options));
        }

        const instance = new OffCanvasCartPlugin(el, {}, 'OffCanvasCart');
        instance.$emitter.publish = jest.fn();

        return instance;
    }

    beforeEach(() => {

        window.router = {
            'frontend.cart.offcanvas': '/checkout/offcanvas',
        };

        window.focusHandler = new FocusHandler();

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

        fireRequestSpy = jest.spyOn(OffCanvasCartPlugin.prototype, '_fireRequest');

        plugin = createPlugin();

        jest.useFakeTimers();
    });

    afterEach(() => {
        fireRequestSpy.mockClear();
        jest.clearAllTimers();
        jest.useRealTimers();
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

    test.each([
        ['enabled', { autoFocus: true }, true],
        ['disabled', { autoFocus: false }, false],
        ['omitted', undefined, false],
    ])('restores quantity focus only when the header autoFocus option is enabled: %s', (description, options, shouldRestoreFocus) => {
        plugin = createPlugin(options);
        plugin.el.dispatchEvent(new Event('click', { bubbles: true }));
        jest.runOnlyPendingTimers();

        const quantitySelector = '[data-focus-id="line-item-offcanvas-quantity-uuid555"]';
        const quantityInput = document.querySelector(quantitySelector);
        const response = document.querySelector('.offcanvas').cloneNode(true);
        response.querySelector(quantitySelector).setAttribute('value', '2');

        jest.spyOn(plugin.client, 'post').mockImplementation((url, data, callback) => callback(response.innerHTML));

        quantityInput.focus();
        expect(document.activeElement).toBe(quantityInput);

        quantityInput.value = '2';
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));

        jest.advanceTimersByTime(800);

        const updatedQuantityInput = document.querySelector(quantitySelector);

        expect(plugin.client.post).toHaveBeenCalledTimes(1);
        expect(updatedQuantityInput).not.toBe(quantityInput);
        expect(updatedQuantityInput.value).toBe('2');
        expect(document.activeElement).toBe(shouldRestoreFocus ? updatedQuantityInput : document.body);
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
