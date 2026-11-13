import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import 'shopware';
import BuyButton from './BuyButton';

const formMarkup = `
    <form id="product-buy-form-1" action="/checkout/line-item/add" data-component="Sw:Product:BuyButton">
        <input type="hidden" name="redirectTo" value="frontend.detail.page">
        <input type="hidden" name="lineItems[1][id]" value="1">
        <input type="hidden" name="lineItems[1][quantity]" value="2" data-quantity-fallback="true">
        <button type="submit">Add to cart</button>
    </form>
`;

const selectorMarkup = '<input type="number" name="lineItems[1][quantity]" value="5" form="product-buy-form-1">';

function createComponent() {
    const component = new BuyButton(document.getElementById('product-buy-form-1'));
    component.init();

    return component;
}

function fallback() {
    return document.querySelector('[data-quantity-fallback="true"]');
}

describe('Sw:Product:BuyButton', () => {
    beforeEach(() => {
        window.PluginManager = { callPluginMethod: vi.fn() };
    });

    afterEach(() => {
        vi.restoreAllMocks();
        document.body.innerHTML = '';
    });

    it('keeps the minimum purchase fallback when no quantity selector is linked', () => {
        document.body.innerHTML = formMarkup;
        const component = createComponent();

        expect(fallback().disabled).toBe(false);
        component.destroy();
    });

    it('disables the fallback when a quantity selector before the form is linked to it', () => {
        document.body.innerHTML = selectorMarkup + formMarkup;
        const component = createComponent();

        expect(fallback().disabled).toBe(true);
        component.destroy();
    });

    it('disables the fallback when a quantity selector after the form is linked to it', () => {
        document.body.innerHTML = formMarkup + selectorMarkup;
        const component = createComponent();

        expect(fallback().disabled).toBe(true);
        component.destroy();
    });

    it('opens the off-canvas cart on submit and stops after destroy', () => {
        document.body.innerHTML = formMarkup;
        const component = createComponent();
        const form = document.getElementById('product-buy-form-1');

        form.dispatchEvent(new Event('submit', { cancelable: true }));
        expect(window.PluginManager.callPluginMethod).toHaveBeenCalledTimes(1);

        component.destroy();
        form.dispatchEvent(new Event('submit', { cancelable: true }));
        expect(window.PluginManager.callPluginMethod).toHaveBeenCalledTimes(1);
    });
});
