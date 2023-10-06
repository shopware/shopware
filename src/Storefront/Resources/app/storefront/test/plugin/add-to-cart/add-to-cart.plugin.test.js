import AddToCartPlugin from 'src/plugin/add-to-cart/add-to-cart.plugin';

const mockOffCanvasInstance = {
    openOffCanvas: (url, data, callback) => {
        callback();
    },
}

// Todo: NEXT-23270 - Remove mock ES module import of PluginManager
jest.mock('src/plugin-system/plugin.manager', () => ({
    __esModule: true,
    default: {
        getPluginInstances: () => {
            return [mockOffCanvasInstance];
        },
    },
}));

/**
 * @package checkout
 */
describe('AddToCartPlugin tests', () => {

    let pluginInstance;

    beforeEach(() => {
        document.body.innerHTML = `
            <form action="/checkout/line-item/add" method="post">
                <input type="hidden" name="redirectTo" value="frontend.cart.offcanvas">
                <input type="hidden" name="redirectParameters" data-redirect-parameters="true" value="{ productId: '36250993b62e49319546ba869b84da77' }" disabled>

                <button>Add to shopping cart</button>
            </form>
        `;

        pluginInstance = new AddToCartPlugin(document.querySelector('form'));

        pluginInstance.$emitter.publish = jest.fn();
    });

    afterEach(() => {
        pluginInstance = undefined;
    });

    test('should init plugin', () => {
        expect(typeof pluginInstance).toBe('object');
    });

    test('should fire events when submitting form', () => {
        const button = document.querySelector('button');

        // Click add to cart button
        button.dispatchEvent(new Event('click', { bubbles: true }));

        expect(pluginInstance.$emitter.publish).toHaveBeenNthCalledWith(1, 'beforeFormSubmit', expect.any(FormData));
        expect(pluginInstance.$emitter.publish).toHaveBeenNthCalledWith(2, 'openOffCanvasCart');
    });

    test('should throw an error when no form can be found', () => {
        document.body.innerHTML = `
            <div class="not-a-form-much-trouble">
                <div data-add-to-cart="true"></div>
            </div>
        `

        expect(() => {
            new AddToCartPlugin(document.querySelector('[data-add-to-cart]'));
        }).toThrowError('No form found for the plugin: AddToCartPlugin');
    });

    test('should init plugin when element is wrapped by form', () => {
        document.body.innerHTML = `
            <form action="/checkout/line-item/add" method="post">
                <div data-add-to-cart="true"></div>
            </form>
        `

        pluginInstance = new AddToCartPlugin(document.querySelector('[data-add-to-cart]'));

        expect(typeof pluginInstance).toBe('object');
    });
});
