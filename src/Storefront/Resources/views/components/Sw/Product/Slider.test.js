import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
// Side effect: registers the global `ShopwareComponent` base class that Slider.js extends.
import 'shopware';
import ProductSlider from './Slider.js';

/**
 * Builds the slider DOM and pins the list dimensions, since happy-dom has no
 * layout engine and reports 0 for clientWidth/scrollWidth/scrollLeft otherwise.
 */
function createSlider({ scrollLeft, clientWidth, scrollWidth }) {
    document.body.innerHTML = `
        <section class="sw-product-slider">
            <div class="sw-product-slider__inner">
                <ul class="sw-product-slider__list list-unstyled"></ul>
                <button class="sw-product-slider__nav-button is--backward" hidden></button>
                <button class="sw-product-slider__nav-button is--forward" hidden></button>
            </div>
        </section>
    `;

    const root = document.querySelector('.sw-product-slider');
    const list = root.querySelector('.sw-product-slider__list');

    Object.defineProperty(list, 'clientWidth', { configurable: true, get: () => clientWidth });
    Object.defineProperty(list, 'scrollWidth', { configurable: true, get: () => scrollWidth });
    Object.defineProperty(list, 'scrollLeft', { configurable: true, writable: true, value: scrollLeft });

    const slider = new ProductSlider(root);
    slider.init();

    return {
        backwardBtn: root.querySelector('.sw-product-slider__nav-button.is--backward'),
        forwardBtn: root.querySelector('.sw-product-slider__nav-button.is--forward'),
    };
}

describe('Sw:Product:Slider navigation arrows', () => {
    beforeEach(() => {
        // happy-dom does not implement ResizeObserver, which the component sets up on init.
        vi.stubGlobal('ResizeObserver', class {
            observe() {}
            unobserve() {}
            disconnect() {}
        });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('hides both arrows when the content fits the scroll container', () => {
        const { backwardBtn, forwardBtn } = createSlider({
            scrollLeft: 0,
            clientWidth: 500,
            scrollWidth: 500,
        });

        expect(backwardBtn.hasAttribute('hidden')).toBe(true);
        expect(forwardBtn.hasAttribute('hidden')).toBe(true);
    });

    it('shows only the forward arrow when overflowing and scrolled to the start', () => {
        const { backwardBtn, forwardBtn } = createSlider({
            scrollLeft: 0,
            clientWidth: 500,
            scrollWidth: 1500,
        });

        expect(backwardBtn.hasAttribute('hidden')).toBe(true);
        expect(forwardBtn.hasAttribute('hidden')).toBe(false);
    });

    it('shows both arrows when scrolled somewhere in the middle', () => {
        const { backwardBtn, forwardBtn } = createSlider({
            scrollLeft: 500,
            clientWidth: 500,
            scrollWidth: 1500,
        });

        expect(backwardBtn.hasAttribute('hidden')).toBe(false);
        expect(forwardBtn.hasAttribute('hidden')).toBe(false);
    });

    it('shows only the backward arrow when scrolled to the end', () => {
        const { backwardBtn, forwardBtn } = createSlider({
            scrollLeft: 1000,
            clientWidth: 500,
            scrollWidth: 1500,
        });

        expect(backwardBtn.hasAttribute('hidden')).toBe(false);
        expect(forwardBtn.hasAttribute('hidden')).toBe(true);
    });
});
