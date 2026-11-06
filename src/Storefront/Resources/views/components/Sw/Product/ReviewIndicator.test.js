import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import 'bootstrap';
import 'shopware';
import ProductReviewIndicator from './ReviewIndicator';

const indicatorMarkup = `
    <div class="sw-product-review-indicator" data-component="Sw:Product:ReviewIndicator">
        <button class="sw-product-review-indicator__link btn btn-link-inline" type="button">5 Reviews</button>
    </div>
`;

const reviewsMarkup = '<section class="sw-product-reviews" data-component="Sw:Product:Reviews"></section>';

const tabPanelMarkup = `
    <div class="sw-tabs">
        <ul class="sw-tabs__nav nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="sw-tab-description" type="button" role="tab"
                        data-bs-toggle="tab" data-bs-target="#sw-tab-pane-description">Description</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="sw-tab-reviews" type="button" role="tab"
                        data-bs-toggle="tab" data-bs-target="#sw-tab-pane-reviews">Reviews</button>
            </li>
        </ul>
        <div class="sw-tabs__content tab-content">
            <div class="tab-pane active" id="sw-tab-pane-description" role="tabpanel"><p>Description</p></div>
            <div class="tab-pane" id="sw-tab-pane-reviews" role="tabpanel">${reviewsMarkup}</div>
        </div>
    </div>
`;

function createComponent() {
    const component = new ProductReviewIndicator(document.querySelector('.sw-product-review-indicator'));
    component.init();

    return component;
}

function clickLink() {
    document.querySelector('.sw-product-review-indicator__link').click();
}

describe('Sw:Product:ReviewIndicator', () => {
    beforeEach(() => {
        Element.prototype.scrollIntoView = vi.fn();
    });

    afterEach(() => {
        vi.restoreAllMocks();
        document.body.innerHTML = '';
    });

    it('opens the tab containing the reviews and scrolls to the tab panel', () => {
        document.body.innerHTML = indicatorMarkup + tabPanelMarkup;
        const component = createComponent();

        clickLink();

        expect(document.getElementById('sw-tab-reviews').classList.contains('active')).toBe(true);
        expect(document.getElementById('sw-tab-pane-reviews').classList.contains('active')).toBe(true);
        expect(document.querySelector('.sw-tabs').scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' });
        component.destroy();
    });

    it('scrolls to the reviews when they are not inside a tab', () => {
        document.body.innerHTML = indicatorMarkup + reviewsMarkup;
        const component = createComponent();

        clickLink();

        expect(document.querySelector('.sw-product-reviews').scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' });
        component.destroy();
    });

    it('does nothing when the page has no reviews element', () => {
        document.body.innerHTML = indicatorMarkup;
        const component = createComponent();

        clickLink();

        expect(Element.prototype.scrollIntoView).not.toHaveBeenCalled();
        component.destroy();
    });

    it('stops reacting to clicks after destroy', () => {
        document.body.innerHTML = indicatorMarkup + reviewsMarkup;
        const component = createComponent();
        component.destroy();

        clickLink();

        expect(Element.prototype.scrollIntoView).not.toHaveBeenCalled();
    });
});
