import { describe, it, expect, vi, beforeEach } from 'vitest';
import { Shopware } from 'shopware';

import 'shopware';

globalThis.PluginManager = { callPluginMethod: vi.fn() };

const { default: BuyButton } = await import('./BuyButton');

function buildEl({ action = '/checkout/add', hasRedirectInput = true, hasRedirectParamInput = true } = {}) {
    const el = document.createElement('form');
    el.setAttribute('action', action);

    if (hasRedirectInput) {
        const input = document.createElement('input');
        input.setAttribute('name', 'redirectTo');
        el.appendChild(input);
    }

    if (hasRedirectParamInput) {
        const input = document.createElement('input');
        input.setAttribute('data-redirect-parameters', 'true');
        el.appendChild(input);
    }

    return el;
}

describe('BuyButton', () => {
    let el;
    let button;

    beforeEach(() => {
        vi.clearAllMocks();
        globalThis.PluginManager.callPluginMethod = vi.fn();
        el = buildEl();
        button = new BuyButton(el, {});
        button.init();
    });

    describe('init', () => {
        it('sets the redirectTo input value to the configured route', () => {
            const input = el.querySelector('[name="redirectTo"]');
            expect(input.value).toBe('frontend.cart.offcanvas');
        });

        it('disables the redirect parameter input', () => {
            const input = el.querySelector('[data-redirect-parameters="true"]');
            expect(input.disabled).toBe(true);
        });

        it('does not throw when redirect input is absent', () => {
            const el2 = buildEl({ hasRedirectInput: false });
            expect(() => new BuyButton(el2, {}).init()).not.toThrow();
        });

        it('does not throw when redirect param input is absent', () => {
            const el2 = buildEl({ hasRedirectParamInput: false });
            expect(() => new BuyButton(el2, {}).init()).not.toThrow();
        });
    });

    describe('onFormSubmit', () => {
        it('prevents the default form submission', () => {
            const event = new Event('submit', { bubbles: true, cancelable: true });
            el.dispatchEvent(event);

            expect(event.defaultPrevented).toBe(true);
        });

        it('emits BuyButton:Submit with the form action URL and serialized form data', () => {
            el.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('BuyButton:Submit', '/checkout/add', {});
        });

        it('calls PluginManager.callPluginMethod to open the offcanvas cart', () => {
            el.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            expect(globalThis.PluginManager.callPluginMethod).toHaveBeenCalledWith(
                'OffCanvasCart', 'openOffCanvas', '/checkout/add', {},
            );
        });

        it('calls emitInterception before emitting BuyButton:Submit', () => {
            el.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            expect(Shopware.emitInterception).toHaveBeenCalledWith(
                'BuyButton:PreSubmit',
                expect.objectContaining({ requestUrl: '/checkout/add' }),
            );
        });

        it('uses intercepted requestUrl when calling PluginManager', () => {
            vi.mocked(Shopware.emitInterception).mockReturnValueOnce({
                requestUrl: '/custom-url',
                formData: null,
            });

            el.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            expect(globalThis.PluginManager.callPluginMethod).toHaveBeenCalledWith(
                'OffCanvasCart',
                'openOffCanvas',
                '/custom-url',
                null,
            );
        });
    });
});
