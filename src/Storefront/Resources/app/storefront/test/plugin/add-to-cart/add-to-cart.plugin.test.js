import AddToCartPlugin from 'src/plugin/add-to-cart/add-to-cart.plugin';

const mockOffCanvasInstance = {
    openOffCanvas: (url, data, callback) => {
        callback();
    },
};

const mockCartWidgetInstance = {
    fetch: jest.fn(),
};

/**
 * @package checkout
 */
describe('AddToCartPlugin tests', () => {

    let pluginInstance;

    beforeEach(() => {
        document.body.innerHTML = `
            <div class="form-wrapper">
                <form action="/checkout/line-item/add" method="post">
                    <input type="hidden" name="redirectTo" value="frontend.cart.offcanvas">
                    <input type="hidden" name="redirectParameters" data-redirect-parameters="true" value="{ productId: '36250993b62e49319546ba869b84da77' }" disabled>

                    <button>Add to shopping cart</button>
                </form>
            </div>
            <template class="js-add-to-cart-alert-template">
                <div class="alert"><span>Product added to cart</span></div>
            </template>
        `;

        window.PluginManager.getPluginInstances = jest.fn((pluginName) => {
            if (pluginName === 'OffCanvasCart') {
                return [mockOffCanvasInstance];
            }
            if (pluginName === 'CartWidget') {
                return [mockCartWidgetInstance];
            }
            return [];
        });

        // Default: offcanvas is enabled
        window.openOffcanvasAfterAddToCart = '1';

        pluginInstance = new AddToCartPlugin(document.querySelector('form'));
        pluginInstance.$emitter.publish = jest.fn();

        // Reset mocks
        mockCartWidgetInstance.fetch.mockClear();

        // Mock fetch for _addToCartWithoutOffcanvas
        global.fetch = jest.fn(() => Promise.resolve({ ok: true }));
    });

    afterEach(() => {
        pluginInstance = undefined;
        delete window.openOffcanvasAfterAddToCart;
        jest.restoreAllMocks();
    });

    test('should init plugin', () => {
        expect(typeof pluginInstance).toBe('object');
    });

    test('should fire events and open offcanvas when submitting form with offcanvas enabled', () => {
        window.openOffcanvasAfterAddToCart = '1';

        const button = document.querySelector('button');
        button.click();

        expect(pluginInstance.$emitter.publish).toHaveBeenNthCalledWith(1, 'beforeFormSubmit', expect.any(FormData));
        expect(pluginInstance.$emitter.publish).toHaveBeenNthCalledWith(2, 'openOffCanvasCart');
    });

    test('should add to cart without offcanvas when offcanvas is disabled', async () => {
        window.openOffcanvasAfterAddToCart = '0';

        const button = document.querySelector('button');
        button.click();

        // Wait for the fetch promise to resolve
        await Promise.resolve();

        expect(global.fetch).toHaveBeenCalledWith('/checkout/line-item/add', {
            method: 'POST',
            body: expect.any(FormData),
        });
        expect(mockCartWidgetInstance.fetch).toHaveBeenCalled();
        expect(pluginInstance.$emitter.publish).toHaveBeenCalledWith('beforeFormSubmit', expect.any(FormData));
        expect(pluginInstance.$emitter.publish).toHaveBeenCalledWith('addToCartWithoutOffcanvas');
    });

    test('should show success alert when adding to cart without offcanvas', async () => {
        window.openOffcanvasAfterAddToCart = '0';

        const button = document.querySelector('button');
        button.click();

        await Promise.resolve();

        const alert = document.querySelector('.add-to-cart-alert');
        expect(alert).not.toBeNull();
        expect(alert.classList.contains('show')).toBe(true);
        expect(alert.classList.contains('d-none')).toBe(false);
    });

    test('should remove existing alert before showing new one', async () => {
        window.openOffcanvasAfterAddToCart = '0';

        const button = document.querySelector('button');

        // First click
        button.click();
        await Promise.resolve();

        // Second click
        button.click();
        await Promise.resolve();

        const alerts = document.querySelectorAll('.add-to-cart-alert');
        expect(alerts.length).toBe(1);
    });

    test('should auto-dismiss alert after delay', async () => {
        jest.useFakeTimers();
        window.openOffcanvasAfterAddToCart = '0';

        const button = document.querySelector('button');
        button.click();

        await Promise.resolve();

        const alert = document.querySelector('.add-to-cart-alert');
        expect(alert.classList.contains('show')).toBe(true);

        // Fast-forward past the dismiss delay
        jest.advanceTimersByTime(3000);

        expect(alert.classList.contains('show')).toBe(false);

        jest.useRealTimers();
    });

    test('should return true for _shouldOpenOffcanvas when flag is undefined', () => {
        delete window.openOffcanvasAfterAddToCart;
        expect(pluginInstance._shouldOpenOffcanvas()).toBe(true);
    });

    test('should fall back to offcanvas when fetch fails', async () => {
        window.openOffcanvasAfterAddToCart = '0';
        global.fetch = jest.fn(() => Promise.resolve({ ok: false }));

        const openOffCanvasSpy = jest.spyOn(pluginInstance, '_openOffCanvasCarts');

        const button = document.querySelector('button');
        button.click();

        // Wait for the fetch promise to resolve and the catch block to execute
        await Promise.resolve();
        await Promise.resolve();

        expect(openOffCanvasSpy).toHaveBeenCalled();
    });

    test('should fall back to offcanvas when fetch throws network error', async () => {
        window.openOffcanvasAfterAddToCart = '0';
        global.fetch = jest.fn(() => Promise.reject(new Error('Network error')));

        const openOffCanvasSpy = jest.spyOn(pluginInstance, '_openOffCanvasCarts');

        const button = document.querySelector('button');
        button.click();

        // Wait for the promise to reject and the catch block to execute
        await Promise.resolve();
        await Promise.resolve();

        expect(openOffCanvasSpy).toHaveBeenCalled();
    });

    test('should handle missing CartWidget instances gracefully', async () => {
        window.openOffcanvasAfterAddToCart = '0';
        window.PluginManager.getPluginInstances = jest.fn((pluginName) => {
            if (pluginName === 'OffCanvasCart') {
                return [mockOffCanvasInstance];
            }
            return []; // No CartWidget instances
        });

        const button = document.querySelector('button');
        button.click();

        await Promise.resolve();

        // Should not throw error and should still show success alert
        const alert = document.querySelector('.add-to-cart-alert');
        expect(alert).not.toBeNull();
    });

    test('should throw an error when no form can be found', () => {
        document.body.innerHTML = `
            <div class="not-a-form-much-trouble">
                <div data-add-to-cart="true"></div>
            </div>
        `;

        expect(() => {
            new AddToCartPlugin(document.querySelector('[data-add-to-cart]'));
        }).toThrowError('No form found for the plugin: AddToCartPlugin');
    });

    test('should init plugin when element is wrapped by form', () => {
        document.body.innerHTML = `
            <form action="/checkout/line-item/add" method="post">
                <div data-add-to-cart="true"></div>
            </form>
        `;

        pluginInstance = new AddToCartPlugin(document.querySelector('[data-add-to-cart]'));

        expect(typeof pluginInstance).toBe('object');
    });

    test('should not show alert if template is missing', async () => {
        window.openOffcanvasAfterAddToCart = '0';

        // Remove the alert template
        document.querySelector('.js-add-to-cart-alert-template').remove();

        const button = document.querySelector('button');
        button.click();

        await Promise.resolve();

        const alert = document.querySelector('.add-to-cart-alert');
        expect(alert).toBeNull();
    });
});
