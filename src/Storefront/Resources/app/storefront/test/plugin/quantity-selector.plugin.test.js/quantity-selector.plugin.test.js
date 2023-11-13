import QuantitySelectorPlugin from 'src/plugin/quantity-selector/quantity-selector.plugin.js';

/**
 * @package checkout
 */

let stepUpSpy;
let stepDownSpy;
let triggerChangeSpy;

describe('QuantitySelectorPlugin tests', () => {

    let plugin;

    beforeEach(() => {
        const QuantitySelectorTemplate = `
        <form action="/"
              class="line-item-quantity-container"
              method="post"
        >

            <div class="input-group line-item-quantity-group" data-quantity-selector="true">
                    <button type="button" class="btn btn-light image-plus-btn js-btn-minus">
                            <svg></svg>
                    </button>
                    <input
                        type="number"
                        name="quantity"
                        class="form-control js-quantity-selector"
                        min="10"
                        max="100"
                        step="1"
                        value="20"
                    />
                    <button type="button" class="btn btn-light image-plus-btn js-btn-plus">
                            <svg></svg>
                    </button>
                </div>
        </form>
    `;

        document.body.innerHTML = QuantitySelectorTemplate;

        const el = document.querySelector('[data-quantity-selector="true"]');

        stepUpSpy = jest.spyOn(QuantitySelectorPlugin.prototype, '_stepUp');
        stepDownSpy = jest.spyOn(QuantitySelectorPlugin.prototype, '_stepDown');
        triggerChangeSpy = jest.spyOn(QuantitySelectorPlugin.prototype, '_triggerChange');

        plugin = new QuantitySelectorPlugin(el);
        plugin.$emitter.publish = jest.fn();

        const mockStepUpFn = jest.fn();

        mockStepUpFn.mockImplementationOnce(() => {
            if (parseInt(plugin._input.value) < parseInt(plugin._input.max)) {
                plugin._input.value++;
            }
        });

        const mockStepDownFn = jest.fn();

        mockStepDownFn.mockImplementationOnce(() => {
            if (parseInt(plugin._input.value) > parseInt(plugin._input.min)) {
                plugin._input.value--;
            }
        });

        plugin._input = Object.assign(
            plugin._input,
            {
                stepUp: mockStepUpFn,
                stepDown: mockStepDownFn,
            }
        );

        jest.useFakeTimers();
    });

    afterEach(() => {
        stepUpSpy.mockClear();
        stepDownSpy.mockClear();
    });

    test('creates plugin instance', () => {
        expect(typeof plugin).toBe('object');
    });

    test('should increase quantity', () => {
        const plusBtn = document.querySelector('.js-btn-plus')
        plusBtn.dispatchEvent(new Event('click', {bubbles: true}));
        expect(plugin._input.value).toBe('21');
        expect(stepUpSpy).toHaveBeenCalledTimes(1);
        expect(triggerChangeSpy).toHaveBeenCalledTimes(1);
    });

    test('should decrease quantity', () => {
        const minusBtn = document.querySelector('.js-btn-minus')
        minusBtn.dispatchEvent(new Event('click', {bubbles: true}));
        expect(plugin._input.value).toBe('19');
        expect(stepDownSpy).toHaveBeenCalledTimes(1);
        expect(triggerChangeSpy).toHaveBeenCalledTimes(1);
    });

    test('should not decrease quantity on min or lower', () => {
        plugin._input.value = 10;
        const minusBtn = document.querySelector('.js-btn-minus')
        minusBtn.dispatchEvent(new Event('click', {bubbles: true}));
        expect(plugin._input.value).toBe('10');

        plugin._input.value = 9;
        minusBtn.dispatchEvent(new Event('click', {bubbles: true}));
        expect(stepDownSpy).toHaveBeenCalledTimes(2);
        expect(triggerChangeSpy).toHaveBeenCalledTimes(0);
    });

    test('should not increase quantity on max or higher', () => {
        plugin._input.value = 100;
        const plusBtn = document.querySelector('.js-btn-plus')
        plusBtn.dispatchEvent(new Event('click', {bubbles: true}));
        expect(plugin._input.value).toBe('100');

        plugin._input.value = 101;
        plusBtn.dispatchEvent(new Event('click', {bubbles: true}));
        expect(plugin._input.value).toBe('101');
        expect(stepUpSpy).toHaveBeenCalledTimes(2);
        expect(triggerChangeSpy).toHaveBeenCalledTimes(0);
    });
});
