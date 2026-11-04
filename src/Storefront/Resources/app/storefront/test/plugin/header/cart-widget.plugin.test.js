import CartWidgetPlugin from 'src/plugin/header/cart-widget.plugin';

/**
 * @package checkout
 */
describe('CartWidgetPlugin tests', () => {
    beforeEach(() => {
        window.router = {
            'frontend.checkout.info': '/shop/widgets/checkout/info',
        };

        global.fetch = jest.fn(() => Promise.resolve({
            status: 200,
            text: () => Promise.resolve('<span>cart</span>'),
        }));

        document.body.innerHTML = '<div data-cart-widget="true"></div>';
    });

    afterEach(() => {
        window.history.pushState({}, '', '/');
        window.sessionStorage.clear();
        jest.restoreAllMocks();
    });

    test('requests the cart widget without tracking parameters', () => {
        window.history.pushState({}, '', '/?foo=bar');

        createPlugin();

        expect(global.fetch).toHaveBeenCalledWith('/shop/widgets/checkout/info', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
    });

    test('forwards the affiliate tracking parameters of the page', () => {
        window.history.pushState({}, '', '/?affiliateCode=affiliate%20code&foo=bar&campaignCode=campaign');

        createPlugin();

        expect(global.fetch).toHaveBeenCalledWith(
            '/shop/widgets/checkout/info?affiliateCode=affiliate+code&campaignCode=campaign',
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        );
    });

    test('skips empty tracking parameters', () => {
        window.history.pushState({}, '', '/?affiliateCode=&campaignCode=campaign');

        createPlugin();

        expect(global.fetch).toHaveBeenCalledWith(
            '/shop/widgets/checkout/info?campaignCode=campaign',
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        );
    });

    test('renders the fetched cart widget', async () => {
        const plugin = createPlugin();

        await new Promise(process.nextTick);

        expect(plugin.el.innerHTML).toBe('<span>cart</span>');
    });
});

function createPlugin() {
    return new CartWidgetPlugin(document.querySelector('[data-cart-widget]'));
}
