import { afterEach, describe, expect, it, vi } from 'vitest';
import 'shopware';
import ProductQuantitySelector from './QuantitySelector';

function createSelector({ value = 1, min = 1, max = 10, step = 1, purchaseLimitUrl = null } = {}) {
    const form = document.createElement('form');
    form.innerHTML = `
        <div class="sw-product-quantity-selector" ${purchaseLimitUrl ? `data-component-options='{"purchaseLimitUrl":"${purchaseLimitUrl}"}'` : ''}>
            <button type="button" class="sw-product-quantity-selector__button--decrease"></button>
            <input class="sw-product-quantity-selector__input" type="number" value="${value}" min="${min}" max="${max}" step="${step}">
            <button type="button" class="sw-product-quantity-selector__button--increase"></button>
            <span class="sw-product-quantity-selector__unit" data-unit-singular="box" data-unit-plural="boxes">box</span>
            <div class="sw-product-quantity-selector__live" data-aria-live-text="Quantity: %quantity% for %product%" data-aria-live-product-name="Product"></div>
        </div>
    `;
    document.body.appendChild(form);

    return {
        form,
        element: form.firstElementChild,
        input: form.querySelector('input'),
    };
}

function createComponent(element, options = {}) {
    const component = new ProductQuantitySelector(element, options);
    component.init();

    return component;
}

const flushPromises = () => Promise.resolve().then(() => Promise.resolve());

describe('Sw:Product:QuantitySelector', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
        document.body.innerHTML = '';
    });

    it.each([
        ['increases', 'stepUp', 2],
        ['decreases', 'stepDown', 1],
    ])('%s with native input stepping', (_, method, expected) => {
        const { element, input } = createSelector({ value: method === 'stepDown' ? 2 : 1 });
        const change = vi.fn();
        input.addEventListener('change', change);
        const component = createComponent(element);

        element.querySelector(`.${method === 'stepUp' ? 'sw-product-quantity-selector__button--increase' : 'sw-product-quantity-selector__button--decrease'}`).click();

        expect(input.value).toBe(String(expected));
        expect(change).toHaveBeenCalledTimes(1);
        component.destroy();
    });

    it('does not step beyond native bounds', () => {
        const { element, input } = createSelector({ value: 10, max: 10 });
        const component = createComponent(element);

        element.querySelector('.sw-product-quantity-selector__button--increase').click();

        expect(input.value).toBe('10');
        component.destroy();
    });

    it('reports native stepping errors', () => {
        const { element, input } = createSelector();
        const error = new Error('Unable to step input');
        const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
        vi.spyOn(input, 'stepUp').mockImplementation(() => {
            throw error;
        });
        const component = createComponent(element);

        element.querySelector('.sw-product-quantity-selector__button--increase').click();

        expect(consoleError).toHaveBeenCalledWith('Could not change the product quantity.', error);
        component.destroy();
    });

    it('updates the unit and live region after a typed quantity change', () => {
        const { element, input } = createSelector();
        const component = createComponent(element);

        input.value = '2';
        input.dispatchEvent(new Event('change', { bubbles: true }));

        expect(element.querySelector('.sw-product-quantity-selector__unit').textContent).toBe('boxes');
        expect(element.querySelector('.sw-product-quantity-selector__live').textContent).toBe('Quantity: 2 for Product');
        component.destroy();
    });

    it('fetches closeout limits only once, adjusts the quantity, and dispatches stock adjusted', async () => {
        const { element, form, input } = createSelector({ value: 7, purchaseLimitUrl: '/limit' });
        const eventSpy = vi.fn();
        form.addEventListener('QuantitySelector/StockAdjusted', eventSpy);
        const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ minPurchase: 2, purchaseSteps: 2, maxPurchase: 6 }) });
        vi.stubGlobal('fetch', fetchMock);
        const component = createComponent(element, { purchaseLimitUrl: '/limit' });

        input.focus();
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(input.min).toBe('2');
        expect(input.max).toBe('6');
        expect(input.step).toBe('2');
        expect(input.value).toBe('6');
        expect(eventSpy).toHaveBeenCalledWith(expect.objectContaining({ detail: { quantity: 6 } }));
        component.destroy();
    });

    it('disables controls and dispatches out of stock when no quantity is available', async () => {
        const { element, form, input } = createSelector({ purchaseLimitUrl: '/limit' });
        const eventSpy = vi.fn();
        form.addEventListener('QuantitySelector/OutOfStock', eventSpy);
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ minPurchase: 1, purchaseSteps: 1, maxPurchase: 0 }) }));
        const component = createComponent(element, { purchaseLimitUrl: '/limit' });

        input.focus();
        await flushPromises();

        expect(element.querySelectorAll('button, input')).toHaveLength(3);
        expect([...element.querySelectorAll('button, input')].every((control) => control.disabled)).toBe(true);
        expect(eventSpy).toHaveBeenCalledTimes(1);
        component.destroy();
    });
});
