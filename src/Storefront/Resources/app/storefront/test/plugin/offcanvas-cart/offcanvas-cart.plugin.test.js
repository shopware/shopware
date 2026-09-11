import OffCanvasCartPlugin from 'src/plugin/offcanvas-cart/offcanvas-cart.plugin';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';

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
                    <input type="number" name="quantity" class="js-offcanvas-cart-change-quantity-number" min="1" max="150" step="1" value="1" data-focus-id="quantity-uuid555">
                    <button type="button" class="js-btn-plus" data-focus-id="quantity-up-uuid555">+</button>
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

    function flushPromises() {
        // Let a finished response pass through the callback before the next queued request starts.
        return new Promise(resolve => jest.requireActual('timers').setImmediate(resolve));
    }

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

    test('change product quantity using select', async () => {
        const el = document.querySelector('.header-cart');

        // Open offcanvas cart with click
        el.dispatchEvent(new Event('click', { bubbles: true }));

        const quantitySelect = document.querySelector('.js-offcanvas-cart-change-quantity');

        // Edit quantity using change event
        quantitySelect.dispatchEvent(new Event('change', { bubbles: true }));
        await plugin._requestQueue;

        expect(plugin.$emitter.publish).toBeCalledWith('beforeFireRequest');
        expect(fireRequestSpy).toHaveBeenCalledTimes(1);

        // Verify updated content after quantity change
        expect(document.querySelector('.offcanvas-body').textContent).toBe('Content after update');
    });

    test('change product quantity using number input', async () => {
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
        await plugin._requestQueue;

        expect(plugin.$emitter.publish).toBeCalledWith('beforeFireRequest');
        expect(fireRequestSpy).toHaveBeenCalledTimes(1);

        // Verify updated content after quantity change
        expect(document.querySelector('.offcanvas-body').textContent).toBe('Content after update');
    });

    test('does not submit a number input change withheld from the form', () => {
        document.querySelector('.header-cart').dispatchEvent(new Event('click', { bubbles: true }));

        const quantityInput = document.querySelector('.js-offcanvas-cart-change-quantity-number');
        // The quantity selector withholds native arrow-key changes until the edit is finished.
        quantityInput.addEventListener('change', event => event.stopPropagation());
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));
        jest.advanceTimersByTime(800);

        expect(fireRequestSpy).not.toHaveBeenCalled();
    });

    test('change product quantity should not send too many requests when spamming the number input', async () => {
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

        await plugin._requestQueue;

        // Change quantity again, this time after waiting long enough
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));

        // Wait for debounce with time from defaults
        jest.advanceTimersByTime(800);
        await plugin._requestQueue;

        expect(plugin.$emitter.publish).toBeCalledWith('beforeFireRequest');

        // Only 2 requests should be fired because the throttling should prevent the first spam inputs
        expect(fireRequestSpy).toHaveBeenCalledTimes(2);
    });

    test('applies a committed change immediately and cancels the delayed update', async () => {
        document.querySelector('.header-cart').dispatchEvent(new Event('click', { bubbles: true }));
        const input = document.querySelector('.js-offcanvas-cart-change-quantity-number');
        const post = jest.spyOn(plugin.client, 'post');

        input.value = '2';
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.value = '3';
        input.dispatchEvent(new CustomEvent('change', { bubbles: true, detail: { submitImmediately: true } }));

        expect(fireRequestSpy).toHaveBeenCalledTimes(1);
        await plugin._requestQueue;
        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][1].get('quantity')).toBe('3');

        jest.advanceTimersByTime(800);
        await plugin._requestQueue;
        expect(post).toHaveBeenCalledTimes(1);
    });

    test('restores focus to the element focused when the delayed request fires', async () => {
        plugin.options.autoFocus = true;
        document.querySelector('.header-cart').dispatchEvent(new Event('click', { bubbles: true }));
        jest.runOnlyPendingTimers();

        const input = document.querySelector('.js-offcanvas-cart-change-quantity-number');
        input.dispatchEvent(new Event('change', { bubbles: true }));
        document.querySelector('.js-btn-plus').focus();
        jest.advanceTimersByTime(800);
        await plugin._requestQueue;

        expect(window.focusHandler.saveFocusState).toHaveBeenCalledWith('offcanvas-cart', '[data-focus-id="quantity-up-uuid555"]');
    });

    test('applies removal and quantity mutations in order and snapshots the submitted quantity', async () => {
        document.querySelector('.header-cart').dispatchEvent(new Event('click', { bubbles: true }));
        const responses = [];
        const serverCart = { a: 1, b: 1 };
        const post = jest.spyOn(plugin.client, 'post').mockImplementation((url, data, callback) => {
            responses.push(() => {
                if (url === '/remove-a') {
                    delete serverCart.a;
                } else {
                    serverCart.b = Number(data.get('quantity'));
                }

                callback(`<div class="offcanvas-body">${JSON.stringify(serverCart)}</div>`, { status: 200 });
            });
        });

        const removeForm = document.querySelector('form');
        removeForm.action = '/remove-a';
        removeForm.classList.add('js-offcanvas-cart-remove-product');
        plugin._registerRemoveProductTriggerEvents();
        removeForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

        const input = document.querySelector('.js-offcanvas-cart-change-quantity-number');
        input.value = '2';
        input.dispatchEvent(new CustomEvent('change', { bubbles: true, detail: { submitImmediately: true } }));
        input.value = '3';
        await flushPromises();
        expect(post).toHaveBeenCalledTimes(1);

        responses.shift()();
        await flushPromises();
        expect(document.querySelector('.offcanvas-body').textContent).toBe('{"b":1}');
        expect(post).toHaveBeenCalledTimes(2);

        responses.shift()();
        await plugin._requestQueue;
        expect(serverCart).toEqual({ b: 2 });
        expect(document.querySelector('.offcanvas-body').textContent).toBe(JSON.stringify(serverCart));
    });

    test('continues with queued mutations after a failed XMLHttpRequest calls back on loadend', async () => {
        document.querySelector('.header-cart').dispatchEvent(new Event('click', { bubbles: true }));
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
        const removeIndicator = jest.spyOn(ElementLoadingIndicatorUtil, 'remove');
        const post = jest.spyOn(plugin.client, 'post')
            .mockImplementationOnce((url, data, callback) => callback('', { status: 0 }))
            .mockImplementationOnce((url, data, callback) => callback('<div class="offcanvas-body">Recovered</div>', { status: 200 }));
        const forms = document.querySelectorAll('form');
        const failedContainer = forms[0].closest('.js-cart-item');

        plugin._fireRequest(forms[0], '.js-cart-item');
        plugin._fireRequest(forms[1], '.js-cart-item');
        await plugin._requestQueue;

        expect(warn).toHaveBeenCalledWith('Unable to update the off-canvas cart.', expect.any(Error));
        expect(removeIndicator).toHaveBeenCalledWith(failedContainer);
        expect(post).toHaveBeenCalledTimes(2);
        expect(document.querySelector('.offcanvas-body').textContent).toBe('Recovered');
        warn.mockRestore();
        removeIndicator.mockRestore();
    });

    test.each(['error', 'abort', 'timeout'])('continues after an XMLHttpRequest %s without a response callback', async (eventName) => {
        document.querySelector('.header-cart').dispatchEvent(new Event('click', { bubbles: true }));
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
        const request = document.createElement('div');
        const post = jest.spyOn(plugin.client, 'post').mockReturnValueOnce(request);
        const forms = document.querySelectorAll('form');

        plugin._fireRequest(forms[0], '.js-cart-item');
        plugin._fireRequest(forms[1], '.js-cart-item');
        await flushPromises();
        expect(post).toHaveBeenCalledTimes(1);

        // HttpClient with internal error handling does not invoke its callback in these cases.
        request.dispatchEvent(new Event(eventName));
        await plugin._requestQueue;

        expect(warn).toHaveBeenCalledWith('Unable to update the off-canvas cart.', expect.any(Error));
        expect(post).toHaveBeenCalledTimes(2);
        expect(document.querySelector('.offcanvas-body').textContent).toBe('Content after update');
        warn.mockRestore();
    });

    test('continues with queued mutations after a response callback throws', async () => {
        document.querySelector('.header-cart').dispatchEvent(new Event('click', { bubbles: true }));
        const error = new Error('Unable to render response');
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
        const post = jest.spyOn(plugin.client, 'post');
        const forms = document.querySelectorAll('form');

        plugin._fireRequest(forms[0], '.js-cart-item', () => { throw error; });
        plugin._fireRequest(forms[1], '.js-cart-item');
        await plugin._requestQueue;

        expect(warn).toHaveBeenCalledWith('Unable to update the off-canvas cart.', error);
        expect(post).toHaveBeenCalledTimes(2);
        expect(document.querySelector('.offcanvas-body').textContent).toBe('Content after update');
        warn.mockRestore();
    });

    test('preserves the callback context and XMLHttpRequest argument', async () => {
        document.querySelector('.header-cart').dispatchEvent(new Event('click', { bubbles: true }));
        const request = { status: 200 };
        jest.spyOn(plugin.client, 'post').mockImplementationOnce((url, data, callback) => callback('Response', request));
        const callback = jest.fn();

        plugin._fireRequest(document.querySelector('form'), '.js-cart-item', callback);
        await plugin._requestQueue;

        expect(callback).toHaveBeenCalledWith('Response', request);
        expect(callback.mock.instances[0]).toBe(plugin);
    });

    test('waits for the shipping refresh before sending the next mutation', async () => {
        document.querySelector('.header-cart').dispatchEvent(new Event('click', { bubbles: true }));
        document.body.insertAdjacentHTML('beforeend', `
            <div class="offcanvas-summary">
                <form action="/shipping"><select name="shippingMethodId"><option value="express">Express</option></select></form>
            </div>`);
        const responses = [];
        const post = jest.spyOn(plugin.client, 'post').mockImplementation((url, data, callback) => { responses.push(callback); });
        const get = jest.spyOn(plugin.client, 'get').mockImplementation((url, callback) => { responses.push(callback); });

        plugin._onChangeShippingMethod({
            preventDefault: jest.fn(),
            target: document.querySelector('.offcanvas-summary select'),
        });
        plugin._fireRequest(document.querySelector('.js-cart-item form'), '.js-cart-item');
        await flushPromises();
        expect(post).toHaveBeenCalledTimes(1);
        expect(get).not.toHaveBeenCalled();

        responses.shift()('');
        await flushPromises();
        expect(get).toHaveBeenCalledWith('/checkout/offcanvas', expect.any(Function), 'text/html');
        expect(post).toHaveBeenCalledTimes(1);

        responses.shift()('<div class="offcanvas-body">Shipping updated</div>');
        await flushPromises();
        expect(document.querySelector('.offcanvas-body').textContent).toBe('Shipping updated');
        expect(post).toHaveBeenCalledTimes(2);

        responses.shift()('<div class="offcanvas-body">Quantity updated</div>');
        await plugin._requestQueue;
        expect(document.querySelector('.offcanvas-body').textContent).toBe('Quantity updated');
    });
});
