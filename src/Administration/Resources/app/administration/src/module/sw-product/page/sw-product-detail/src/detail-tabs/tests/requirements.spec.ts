import { createProductDetailTabs, type ProductDetailTabsInput } from '../private';

/**
 * @sw-package inventory
 */

/**
 * Where the tabs navigated to, in order.
 */
const navigations: unknown[] = [];

/**
 * The tabs of a parent product in advanced mode without errors, with the given changes.
 * The labels are the snippet keys, and every navigation lands in `navigations`.
 */
function getTabs(changes: Partial<ProductDetailTabsInput> = {}) {
    return createProductDetailTabs({
        productId: 'product-id',
        isChild: false,
        showModeSetting: true,
        generalError: null,
        crossSellingError: null,
        translate: (key) => key,
        navigate: (route) => navigations.push(route),
        ...changes,
    });
}

// The product detail page splits a product into tabs, each one its own route under the product's id.
// The advanced mode decides whether the merchant sees more than the general and specifications tabs.
// The general and cross selling tabs mark themselves when a field on them has an error.
describe('the tabs of the product detail page', () => {
    beforeEach(() => {
        navigations.length = 0;
    });

    /**
     * @requirement A merchant in advanced mode reaches every part of a product through its tabs.
     */
    it('shows every tab in advanced mode', () => {
        // ACT
        const names = getTabs().map((tab) => tab.name);

        // ASSERT
        expect(names).toEqual([
            'sw.product.detail.base',
            'sw.product.detail.specifications',
            'sw.product.detail.prices',
            'sw.product.detail.variants',
            'sw.product.detail.layout',
            'sw.product.detail.seo',
            'sw.product.detail.crossSelling',
            'sw.product.detail.reviews',
        ]);
    });

    /**
     * @requirement A merchant who turned the advanced mode off sees only the general and specifications tabs.
     */
    it('shows only the basic tabs without the advanced mode', () => {
        // ACT
        const names = getTabs({ showModeSetting: false }).map((tab) => tab.name);

        // ASSERT
        expect(names).toEqual(['sw.product.detail.base', 'sw.product.detail.specifications']);
    });

    /**
     * @requirement A merchant cannot create variants of a variant.
     */
    it('hides the variants tab for a variant', () => {
        // ACT
        const names = getTabs({ isChild: true }).map((tab) => tab.name);

        // ASSERT
        expect(names).not.toContain('sw.product.detail.variants');
    });

    /**
     * @requirement A merchant who clicks a tab opens that part of the same product.
     */
    it('opens the route of a tab for the same product', () => {
        // ARRANGE
        const seoTab = getTabs().find((tab) => tab.name === 'sw.product.detail.seo');

        // ACT
        seoTab?.onClick();

        // ASSERT
        expect(navigations).toEqual([{ name: 'sw.product.detail.seo', params: { id: 'product-id' } }]);
    });

    /**
     * @requirement A merchant sees which tab holds a field that failed to save.
     */
    it('marks the general and cross selling tabs with their errors', () => {
        // ACT
        const tabs = getTabs({ generalError: { code: 'general' }, crossSellingError: { code: 'cross' } });

        // ASSERT
        expect(tabs.find((tab) => tab.name === 'sw.product.detail.base')?.hasError).toEqual({ code: 'general' });
        expect(tabs.find((tab) => tab.name === 'sw.product.detail.crossSelling')?.hasError).toEqual({ code: 'cross' });
    });
});
