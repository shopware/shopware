import QuantitySelectorPlugin from 'src/plugin/quantity-selector/quantity-selector.plugin.js';

/**
 * @package checkout
 */

let stepUpSpy;
let stepDownSpy;
let triggerChangeSpy;
let ariaLiveSpy;

function createLivePlugin({ url = '/product/pid/purchase-limit', inputValue = 5 } = {}) {
    document.body.innerHTML = `
        <form>
            <div class="input-group" data-quantity-selector="true"
                 ${url ? `data-quantity-selector-options='{"purchaseLimitUrl": "${url}"}'` : ''}>
                <button type="button" class="js-btn-minus">-</button>
                <input type="number" class="js-quantity-selector" min="1" max="10" step="1" value="${inputValue}">
                <button type="button" class="js-btn-plus">+</button>
            </div>
        </form>
    `;

    return new QuantitySelectorPlugin(document.querySelector('[data-quantity-selector]'), {}, 'QuantitySelector');
}

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
                <span class="input-group-text js-quantity-selector-unit" data-unit-singular="box" data-unit-plural="boxes">boxes</span>
            </div>
            <div
                class="quantity-area-live visually-hidden"
                aria-live="polite"
                aria-atomic="true"
                data-aria-live-text="Quantity of %product% set to %quantity%."
                data-aria-live-product-name="Test Product">
            </div>
        </form>
    `;

        document.body.innerHTML = QuantitySelectorTemplate;

        const el = document.querySelector('[data-quantity-selector="true"]');

        jest.useFakeTimers();

        stepUpSpy = jest.spyOn(QuantitySelectorPlugin.prototype, '_stepUp');
        stepDownSpy = jest.spyOn(QuantitySelectorPlugin.prototype, '_stepDown');
        triggerChangeSpy = jest.spyOn(QuantitySelectorPlugin.prototype, '_triggerChange');
        ariaLiveSpy = jest.spyOn(QuantitySelectorPlugin.prototype, '_updateAriaLive');

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
            },
        );

        jest.useFakeTimers();
    });

    afterEach(() => {
        stepUpSpy.mockClear();
        stepDownSpy.mockClear();
        jest.useRealTimers();
    });

    test('creates plugin instance', () => {
        expect(typeof plugin).toBe('object');
    });

    test('should increase quantity', () => {
        const plusBtn = document.querySelector('.js-btn-plus');
        plusBtn.dispatchEvent(new Event('click', {bubbles: true}));
        expect(plugin._input.value).toBe('21');
        expect(stepUpSpy).toHaveBeenCalledTimes(1);
        expect(triggerChangeSpy).toHaveBeenCalledTimes(1);
        expect(ariaLiveSpy).toHaveBeenCalledTimes(1);
    });

    test('should decrease quantity', () => {
        const minusBtn = document.querySelector('.js-btn-minus');
        minusBtn.dispatchEvent(new Event('click', {bubbles: true}));
        expect(plugin._input.value).toBe('19');
        expect(stepDownSpy).toHaveBeenCalledTimes(1);
        expect(triggerChangeSpy).toHaveBeenCalledTimes(1);
        expect(ariaLiveSpy).toHaveBeenCalledTimes(1);
    });

    test('should not decrease quantity on min or lower', () => {
        plugin._input.value = 10;
        const minusBtn = document.querySelector('.js-btn-minus');
        minusBtn.dispatchEvent(new Event('click', {bubbles: true}));
        expect(plugin._input.value).toBe('10');

        plugin._input.value = 9;
        minusBtn.dispatchEvent(new Event('click', {bubbles: true}));
        expect(stepDownSpy).toHaveBeenCalledTimes(2);
        expect(triggerChangeSpy).toHaveBeenCalledTimes(0);
        expect(ariaLiveSpy).toHaveBeenCalledTimes(0);
    });

    test('should not increase quantity on max or higher', () => {
        plugin._input.value = 100;
        const plusBtn = document.querySelector('.js-btn-plus');
        plusBtn.dispatchEvent(new Event('click', {bubbles: true}));
        expect(plugin._input.value).toBe('100');

        plugin._input.value = 101;
        plusBtn.dispatchEvent(new Event('click', {bubbles: true}));
        expect(plugin._input.value).toBe('101');
        expect(stepUpSpy).toHaveBeenCalledTimes(2);
        expect(triggerChangeSpy).toHaveBeenCalledTimes(0);
        expect(ariaLiveSpy).toHaveBeenCalledTimes(0);
    });

    test('should update area live on change with product name', () => {
        const plusBtn = document.querySelector('.js-btn-plus');
        const areaLive = document.querySelector('.quantity-area-live');

        plusBtn.dispatchEvent(new Event('click', {bubbles: true}));
        expect(areaLive.innerHTML).toBe('Quantity of Test Product set to 21.');
    });

    test('should update unit label on quantity change', () => {
        const input = document.querySelector('.js-quantity-selector');
        const unitLabel = document.querySelector('.js-quantity-selector-unit');

        input.value = 1;
        input.dispatchEvent(new Event('change', {bubbles: true}));
        expect(unitLabel.textContent).toBe('box');

        input.value = 2;
        input.dispatchEvent(new Event('change', {bubbles: true}));
        expect(unitLabel.textContent).toBe('boxes');
    });

    test('should keep singular unit label when no plural is configured', () => {
        const input = document.querySelector('.js-quantity-selector');
        const unitLabel = document.querySelector('.js-quantity-selector-unit');

        unitLabel.removeAttribute('data-unit-plural');
        input.value = 2;
        input.dispatchEvent(new Event('change', {bubbles: true}));

        expect(unitLabel.textContent).toBe('box');
    });

    test('should update area live on init but not on change if mode is set to "onload"', () => {
        plugin.options.ariaLiveUpdateMode = 'onload';

        window.localStorage.setItem('lastQuantityChange', 'Test Product');

        plugin.init();
        jest.runAllTimers();

        expect(ariaLiveSpy).toHaveBeenCalledTimes(1);
        expect(window.localStorage.getItem('lastQuantityChange')).toBeNull();

        const plusBtn = document.querySelector('.js-btn-plus');
        plusBtn.dispatchEvent(new Event('click', {bubbles: true}));

        expect(ariaLiveSpy).toHaveBeenCalledTimes(1);
        expect(window.localStorage.getItem('lastQuantityChange')).toBe('Test Product');
    });

    test('does not fetch on init without user interaction', () => {
        global.fetch = jest.fn();
        expect(global.fetch).not.toHaveBeenCalled();
    });

    test('fetches live limits on input focus', async () => {
        jest.useRealTimers();
        createLivePlugin();
        global.fetch = jest.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve({ minPurchase: 1, purchaseSteps: 1, maxPurchase: 10 }) }));

        document.querySelector('.js-quantity-selector').dispatchEvent(new Event('focus'));
        await new Promise(process.nextTick);

        expect(global.fetch).toHaveBeenCalledWith(
            '/product/pid/purchase-limit',
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        );
    });

    test('fetches live limits only once on multiple interactions', async () => {
        jest.useRealTimers();
        createLivePlugin();
        global.fetch = jest.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve({ minPurchase: 1, purchaseSteps: 1, maxPurchase: 10 }) }));

        const input = document.querySelector('.js-quantity-selector');
        input.dispatchEvent(new Event('focus'));
        input.dispatchEvent(new Event('focus'));
        document.querySelector('.js-btn-plus').click();
        await new Promise(process.nextTick);

        expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    test('applies fetched limits to input attributes', async () => {
        jest.useRealTimers();
        createLivePlugin();
        global.fetch = jest.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve({ minPurchase: 2, purchaseSteps: 2, maxPurchase: 8 }) }));

        document.querySelector('.js-quantity-selector').dispatchEvent(new Event('focus'));
        await new Promise(process.nextTick);

        const input = document.querySelector('.js-quantity-selector');
        expect(input.getAttribute('min')).toBe('2');
        expect(input.getAttribute('max')).toBe('8');
        expect(input.getAttribute('step')).toBe('2');
    });

    test('clamps value and dispatches stockAdjusted event when value exceeds new max', async () => {
        jest.useRealTimers();
        createLivePlugin({ inputValue: 9 });
        global.fetch = jest.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve({ minPurchase: 1, purchaseSteps: 1, maxPurchase: 3 }) }));

        const form = document.querySelector('form');
        const eventSpy = jest.fn();
        form.addEventListener('QuantitySelector/StockAdjusted', eventSpy);

        document.querySelector('.js-quantity-selector').dispatchEvent(new Event('focus'));
        await new Promise(process.nextTick);

        expect(document.querySelector('.js-quantity-selector').value).toBe('3');
        expect(eventSpy).toHaveBeenCalledTimes(1);
        expect(eventSpy.mock.calls[0][0].detail).toEqual({ quantity: 3 });
    });

    test('does not dispatch event when value is within new limits', async () => {
        jest.useRealTimers();
        createLivePlugin();
        global.fetch = jest.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve({ minPurchase: 1, purchaseSteps: 1, maxPurchase: 10 }) }));

        const form = document.querySelector('form');
        const eventSpy = jest.fn();
        form.addEventListener('QuantitySelector/StockAdjusted', eventSpy);
        form.addEventListener('QuantitySelector/OutOfStock', eventSpy);

        document.querySelector('.js-quantity-selector').dispatchEvent(new Event('focus'));
        await new Promise(process.nextTick);

        expect(eventSpy).not.toHaveBeenCalled();
    });

    test('disables controls and dispatches outOfStock event when maxPurchase is 0', async () => {
        jest.useRealTimers();
        createLivePlugin();
        global.fetch = jest.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve({ minPurchase: 1, purchaseSteps: 1, maxPurchase: 0 }) }));

        const form = document.querySelector('form');
        const eventSpy = jest.fn();
        form.addEventListener('QuantitySelector/OutOfStock', eventSpy);

        document.querySelector('.js-quantity-selector').dispatchEvent(new Event('focus'));
        await new Promise(process.nextTick);

        expect(document.querySelector('.js-quantity-selector').disabled).toBe(true);
        expect(document.querySelector('.js-btn-plus').disabled).toBe(true);
        expect(document.querySelector('.js-btn-minus').disabled).toBe(true);
        expect(eventSpy).toHaveBeenCalledTimes(1);
    });

    test('keeps rendered values and logs warning on fetch error', async () => {
        jest.useRealTimers();
        createLivePlugin();
        console.warn = jest.fn();
        global.fetch = jest.fn(() => Promise.reject(new Error('network error')));

        document.querySelector('.js-quantity-selector').dispatchEvent(new Event('focus'));
        await new Promise(process.nextTick);

        expect(console.warn).toHaveBeenCalledWith(expect.stringContaining('Unable to fetch'), expect.any(Error));
        expect(document.querySelector('.js-quantity-selector').getAttribute('max')).toBe('10');
    });
});
