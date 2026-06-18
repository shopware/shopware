import ProductSliderPlugin from 'src/plugin/slider/product-slider.plugin';
import NativeEventEmitter from 'src/helper/emitter.helper';

/**
 * @jest-environment jsdom
 */
describe('ProductSliderPlugin tests', () => {
    function createSlider(width, { withCmsWrapper = true } = {}) {
        const slider = `
            <div class="base-slider product-slider js-slider-initialized" data-product-slider="true" data-product-slider-options="">
                <div class="product-slider-container" data-product-slider-container="true"></div>
            </div>
        `;

        // 'js-slider-initialized' skips the real tiny-slider boot
        document.body.innerHTML = withCmsWrapper
            ? `<div class="cms-element-product-slider has-vertical-alignment"><div class="cms-element-alignment">${slider}</div></div>`
            : slider;

        const element = document.querySelector('.base-slider');

        // jsdom has no layout engine, fake the resolved width on the element used as width source
        element.style.padding = '0px';
        const widthSource = document.querySelector('.cms-element-product-slider') ?? element;
        jest.spyOn(widthSource, 'clientWidth', 'get').mockReturnValue(width);

        return new ProductSliderPlugin(element);
    }

    beforeEach(() => {
        window.breakpoints = {
            lg: 992,
            md: 768,
            sm: 576,
            xl: 1200,
            xxl: 1400,
            xs: 0,
        };

        window.PluginManager = {
            getPluginInstancesFromElement: () => new Map(),
            getPlugin: () => ({ get: () => [] }),
            initializePlugins: jest.fn(),
        };

        document.$emitter = new NativeEventEmitter();
    });

    test('plugin can be instantiated', () => {
        const plugin = createSlider(1360);

        expect(typeof plugin).toBe('object');
    });

    test('_addItemLimit derives the item count from the container width', () => {
        const plugin = createSlider(1360);

        plugin._sliderSettings = { gutter: 30 };
        plugin.options.productboxMinWidth = '300px';

        plugin._addItemLimit();

        // floor(1360 / (300 + 30)) = 4
        expect(plugin._sliderSettings.items).toBe(4);
    });

    test('_addItemLimit never resolves to less than one item', () => {
        const plugin = createSlider(100);

        plugin._sliderSettings = { gutter: 30 };
        plugin.options.productboxMinWidth = '300px';

        plugin._addItemLimit();

        expect(plugin._sliderSettings.items).toBe(1);
    });

    test('_getInnerWidth measures the surrounding cms element, not the content-sized slider', () => {
        const plugin = createSlider(1360);

        // the slider element itself would report a different (content-based) width
        jest.spyOn(plugin.el, 'clientWidth', 'get').mockReturnValue(300);

        expect(plugin._getInnerWidth()).toBe(1360);
    });

    test('_getInnerWidth falls back to the slider element when no cms wrapper exists', () => {
        const plugin = createSlider(800, { withCmsWrapper: false });

        expect(plugin._getInnerWidth()).toBe(800);
    });
});
