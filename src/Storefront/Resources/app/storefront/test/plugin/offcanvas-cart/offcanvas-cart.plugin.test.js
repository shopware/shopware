import DeviceDetection from 'src/helper/device-detection.helper';
import OffCanvasCartPlugin from 'src/plugin/offcanvas-cart/offcanvas-cart.plugin';

/**
 * @package checkout
 */

let fireRequestSpy;

describe('OffCanvasCartPlugin tests', () => {
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

    let plugin;

    beforeEach(() => {
        jest.spyOn(DeviceDetection, 'isTouchDevice').mockReturnValue(false);

        global.fetch = jest.fn((url, init) => {
            // Of we see a request body, we have a POST request.
            if (init.body) {
                return Promise.resolve({
                    text: () => Promise.resolve('<div class="offcanvas-body">Content after update</div>'),
                });
            }
            return Promise.resolve({
                text: () => Promise.resolve(offCanvasCartTemplate),
            });
        });

        window.router = {
            'frontend.cart.offcanvas': '/checkout/offcanvas',
        };

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
            // @todo: Remove when upstream issue https://github.com/twbs/bootstrap/issues/42503 is resolved.
            _addFocusTrapGuard: jest.fn(),
            _removeFocusTrapGuard: jest.fn(),
        };

        document.body.innerHTML = '<div class="header-cart"><a class="header-cart-btn">€ 0,00</a></div>';

        window.PluginManager = {
            initializePluginsInParentElement: jest.fn(),

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

        jest.useFakeTimers({ doNotFake: ['nextTick'] });
    });

    afterEach(() => {
        jest.useRealTimers();
        fireRequestSpy.mockClear();
    });

    test('creates plugin instance', () => {
        expect(typeof plugin).toBe('object');
    });

    test('open offcanvas cart', async () => {
        const el = document.querySelector('.header-cart');

        // Open offcanvas cart with click
        el.dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        expect(plugin.$emitter.publish).toHaveBeenCalledWith('offCanvasOpened', { response: expect.any(String) });
        expect(document.querySelector('.offcanvas.cart-offcanvas')).toBeTruthy();
        expect(document.querySelector('.cart-item-product')).toBeTruthy();
    });

    test('change product quantity using select', async () => {
        const el = document.querySelector('.header-cart');

        // Open offcanvas cart with click
        el.dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        const quantitySelect = document.querySelector('.js-offcanvas-cart-change-quantity');

        // Edit quantity using change event
        quantitySelect.dispatchEvent(new Event('change', { bubbles: true }));
        await new Promise(process.nextTick);

        expect(plugin.$emitter.publish).toHaveBeenCalledWith('beforeFireRequest');
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

        await new Promise(process.nextTick);

        const quantityInput = document.querySelector('.js-offcanvas-cart-change-quantity-number');

        // Edit quantity using number input
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));

        // Wait for debounce with time from defaults
        await jest.advanceTimersByTime(800);
        await new Promise(process.nextTick);

        expect(plugin.$emitter.publish).toHaveBeenCalledWith('beforeFireRequest');
        expect(fireRequestSpy).toHaveBeenCalledTimes(1);

        // Verify updated content after quantity change
        expect(document.querySelector('.offcanvas-body').textContent).toBe('Content after update');
    });

    test('restores the focus to the element focused when the delayed request fires', async () => {
        plugin.options.autoFocus = true;

        document.querySelector('.header-cart').dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);
        // Let the opened offcanvas take the initial focus before the user interacts.
        jest.runOnlyPendingTimers();

        const quantityInput = document.querySelector('.js-offcanvas-cart-change-quantity-number');
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));

        // The user moves on to the `[+]` button while the request is still delayed.
        document.querySelector('.js-btn-plus').focus();
        await jest.advanceTimersByTime(800);
        await new Promise(process.nextTick);

        expect(window.focusHandler.saveFocusState).toHaveBeenCalledWith('offcanvas-cart', '[data-focus-id="quantity-up-uuid555"]');
    });

    test('applies removal and quantity mutations in order and renders the final cart', async () => {
        document.querySelector('.header-cart').dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        const responses = [];
        const serverCart = { a: 1, b: 1 };
        global.fetch = jest.fn((url, init) => new Promise((resolve) => {
            responses.push(() => {
                if (url === '/remove-a') {
                    delete serverCart.a;
                } else {
                    serverCart.b = Number(init.body.get('quantity'));
                }

                resolve({ text: () => Promise.resolve(`<div class="offcanvas-body">${JSON.stringify(serverCart)}</div>`) });
            });
        }));

        const removeForm = document.querySelector('form');
        removeForm.action = '/remove-a';
        removeForm.classList.add('js-offcanvas-cart-remove-product');
        plugin._registerRemoveProductTriggerEvents();
        removeForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

        const input = document.querySelector('.js-offcanvas-cart-change-quantity-number');
        input.value = '2';
        input.dispatchEvent(new CustomEvent('change', {
            bubbles: true,
            detail: { submitImmediately: true },
        }));
        // Queued requests must retain the submitted value even if the old form changes.
        input.value = '3';
        await new Promise(process.nextTick);
        expect(global.fetch).toHaveBeenCalledTimes(1);

        responses.shift()();
        await new Promise(process.nextTick);
        expect(document.querySelector('.offcanvas-body').textContent).toBe('{"b":1}');
        expect(global.fetch).toHaveBeenCalledTimes(2);

        responses.shift()();
        await new Promise(process.nextTick);
        expect(serverCart).toEqual({ b: 2 });
        expect(document.querySelector('.offcanvas-body').textContent).toBe(JSON.stringify(serverCart));
    });

    test('continues with queued mutations after a failed request', async () => {
        document.querySelector('.header-cart').dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        const error = new Error('Connection failed');
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
        global.fetch = jest.fn()
            .mockRejectedValueOnce(error)
            .mockResolvedValueOnce({ text: () => Promise.resolve('<div class="offcanvas-body">Recovered</div>') });
        const forms = document.querySelectorAll('form');
        plugin._fireRequest(forms[0], '.js-cart-item');
        plugin._fireRequest(forms[1], '.js-cart-item');
        await new Promise(process.nextTick);

        expect(warn).toHaveBeenCalledWith('Unable to update the off-canvas cart.', error);
        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(document.querySelector('.offcanvas-body').textContent).toBe('Recovered');
    });

    test('waits for the shipping refresh before sending the next mutation', async () => {
        document.querySelector('.header-cart').dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);
        document.body.insertAdjacentHTML('beforeend', `
            <div class="offcanvas-summary">
                <form action="/shipping"><select name="shippingMethodId"><option value="express">Express</option></select></form>
            </div>`);

        const responses = [];
        global.fetch = jest.fn(() => new Promise(resolve => responses.push(resolve)));
        plugin._onChangeShippingMethod({
            preventDefault: jest.fn(),
            target: document.querySelector('.offcanvas-summary select'),
        });
        plugin._fireRequest(document.querySelector('.js-cart-item form'), '.js-cart-item');
        await new Promise(process.nextTick);
        expect(global.fetch).toHaveBeenCalledTimes(1);

        responses.shift()({ text: () => Promise.resolve('') });
        await new Promise(process.nextTick);
        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(global.fetch.mock.calls[1][0]).toBe('/checkout/offcanvas');

        responses.shift()({ text: () => Promise.resolve('<div class="offcanvas-body">Shipping updated</div>') });
        await new Promise(process.nextTick);
        expect(global.fetch).toHaveBeenCalledTimes(3);
        expect(global.fetch.mock.calls[2][1].method).toBe('POST');

        responses.shift()({ text: () => Promise.resolve('<div class="offcanvas-body">Quantity updated</div>') });
        await new Promise(process.nextTick);
        expect(document.querySelector('.offcanvas-body').textContent).toBe('Quantity updated');
    });

    test('does not fire a request for a change the quantity selector withholds', async () => {
        const el = document.querySelector('.header-cart');

        // Open offcanvas cart with click
        el.dispatchEvent(new Event('click', {
            bubbles: true,
        }));

        await new Promise(process.nextTick);

        const quantityInput = document.querySelector('.js-offcanvas-cart-change-quantity-number');

        // The `QuantitySelectorPlugin` keeps `change` events on the input while the user is
        // still picking a value, so they must not reach the listener of this plugin.
        quantityInput.addEventListener('change', event => event.stopPropagation());
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));

        await jest.advanceTimersByTime(800);
        await new Promise(process.nextTick);

        expect(fireRequestSpy).not.toHaveBeenCalled();
    });

    test('change product quantity should not send too many requests when spamming the number input', async () => {
        const el = document.querySelector('.header-cart');

        // Open offcanvas cart with click
        el.dispatchEvent(new Event('click', {
            bubbles: true,
        }));

        await new Promise(process.nextTick);

        const quantityInput = document.querySelector('.js-offcanvas-cart-change-quantity-number');

        // Changing quantity 3 times directly behind each other to simulate spamming the input
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));

        // Wait for debounce with time from defaults
        jest.advanceTimersByTime(800);
        await new Promise(process.nextTick);

        // Change quantity again, this time after waiting long enough
        quantityInput.dispatchEvent(new Event('change', { bubbles: true }));

        // Wait for debounce with time from defaults
        jest.advanceTimersByTime(800);
        await new Promise(process.nextTick);

        expect(plugin.$emitter.publish).toHaveBeenCalledWith('beforeFireRequest');

        // Only 2 requests should be fired because the throttling should prevent the first spam inputs
        expect(fireRequestSpy).toHaveBeenCalledTimes(2);
    });
});
