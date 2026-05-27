import ProductSliderPlugin from 'src/plugin/slider/product-slider.plugin';
import NativeEventEmitter from 'src/helper/emitter.helper';

/**
 * @jest-environment jsdom
 */
describe('ProductSliderPlugin tests', () => {
    let resizeCallback = undefined;
    let observeMock = jest.fn();
    let disconnectMock = jest.fn();

    function createSlider(innerWidth) {
        // 'js-slider-initialized' skips the real tiny-slider boot; the observer tests drive the methods directly
        document.body.innerHTML = `
            <div class="base-slider product-slider js-slider-initialized" data-product-slider="true" data-product-slider-options="">
                <div class="product-slider-container" data-product-slider-container="true"></div>
            </div>
        `;

        const element = document.querySelector('.base-slider');

        // jsdom has no layout engine, fake the resolved inner width and the padding it subtracts
        element.style.padding = '0px';
        jest.spyOn(element, 'clientWidth', 'get').mockReturnValue(innerWidth);

        const plugin = new ProductSliderPlugin(element);

        return plugin;
    }

    beforeEach(() => {
        resizeCallback = undefined;
        observeMock = jest.fn();
        disconnectMock = jest.fn();

        window.ResizeObserver = jest.fn().mockImplementation((callback) => {
            resizeCallback = callback;

            return {
                observe: observeMock,
                disconnect: disconnectMock,
            };
        });

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

    test('observes the container to recalculate the item limit once layout settled', () => {
        const plugin = createSlider(1360);
        plugin._slider = {};

        plugin._recalculateItemLimitWhenSettled();

        expect(observeMock).toHaveBeenCalledWith(plugin.el);
    });

    test('rebuilds the slider when the settled width changes the item limit', () => {
        const plugin = createSlider(1360);
        plugin._slider = {};
        plugin._sliderSettings = { gutter: 30, items: 1 };
        plugin.options.productboxMinWidth = '300px';

        const spyRebuild = jest.spyOn(plugin, 'rebuild').mockImplementation(() => {});

        plugin._recalculateItemLimitWhenSettled();
        resizeCallback();

        expect(plugin._sliderSettings.items).toBe(4);
        expect(spyRebuild).toHaveBeenCalled();
    });

    test('does not rebuild when the item limit is unchanged', () => {
        const plugin = createSlider(1360);
        plugin._slider = {};
        plugin._sliderSettings = { gutter: 30, items: 4 };
        plugin.options.productboxMinWidth = '300px';

        const spyRebuild = jest.spyOn(plugin, 'rebuild').mockImplementation(() => {});

        plugin._recalculateItemLimitWhenSettled();
        resizeCallback();

        expect(spyRebuild).not.toHaveBeenCalled();
    });

    test('disconnects the resize observer on destroy', () => {
        const plugin = createSlider(1360);
        plugin._slider = {};

        plugin._recalculateItemLimitWhenSettled();
        plugin.destroy();

        expect(disconnectMock).toHaveBeenCalled();
    });
});
