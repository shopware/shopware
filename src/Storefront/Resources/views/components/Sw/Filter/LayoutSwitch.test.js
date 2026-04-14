import { describe, it, expect, vi, beforeEach } from 'vitest';
import { Shopware } from 'shopware';

import 'shopware';
const { default: LayoutSwitch } = await import('./LayoutSwitch');

function buildEl() {
    const el = document.createElement('div');
    el.innerHTML = `
        <button data-layout="default" class="is--active">Default</button>
        <button data-layout="horizontal">Horizontal</button>
    `;
    return el;
}

describe('LayoutSwitch', () => {
    let el;
    let layoutSwitch;

    beforeEach(() => {
        vi.clearAllMocks();
        el = buildEl();
        layoutSwitch = new LayoutSwitch(el, {});
        layoutSwitch.init();
    });

    it('emits LayoutSwitch:Change with the paramName option and clicked layout', () => {
        const btn = el.querySelector('[data-layout="horizontal"]');
        btn.click();

        expect(Shopware.emit).toHaveBeenCalledWith('LayoutSwitch:Change', 'layout', 'horizontal');
    });

    it('dispatches a LayoutSwitch:Change custom event on the element', () => {
        const handler = vi.fn();
        el.addEventListener('LayoutSwitch:Change', handler);

        el.querySelector('[data-layout="horizontal"]').click();

        expect(handler).toHaveBeenCalled();
        expect(handler.mock.calls[0][0].detail).toEqual({ layout: 'horizontal' });
    });

    it('adds is--active class to the clicked button', () => {
        const btn = el.querySelector('[data-layout="horizontal"]');
        btn.click();

        expect(btn.classList.contains('is--active')).toBe(true);
    });

    it('removes is--active from all buttons before adding it to the clicked one', () => {
        const defaultBtn = el.querySelector('[data-layout="default"]');
        const horizontalBtn = el.querySelector('[data-layout="horizontal"]');

        horizontalBtn.click();

        expect(defaultBtn.classList.contains('is--active')).toBe(false);
        expect(horizontalBtn.classList.contains('is--active')).toBe(true);
    });

    it('uses a custom paramName option', () => {
        el = buildEl();
        layoutSwitch = new LayoutSwitch(el, { paramName: 'view' });
        layoutSwitch.init();

        el.querySelector('[data-layout="horizontal"]').click();

        expect(Shopware.emit).toHaveBeenCalledWith('LayoutSwitch:Change', 'view', 'horizontal');
    });
});
