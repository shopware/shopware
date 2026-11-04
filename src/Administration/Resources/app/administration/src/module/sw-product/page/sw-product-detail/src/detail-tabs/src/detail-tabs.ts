/**
 * @sw-package inventory
 */

type TabRoute = { name: string; params: { id: unknown } };

/**
 * One tab of the product detail page, in the shape `mt-tabs` takes.
 *
 * @private
 */
export type ProductDetailTab = {
    label: string;
    name: string;
    onClick: () => void;
    hasError?: unknown;
};

/**
 * What decides the tabs of a product: the product's route id, whether it is a variant, the advanced mode, and the errors of the tabs that report some.
 *
 * @private
 */
export type ProductDetailTabsInput = {
    productId: unknown;
    isChild: boolean;
    showModeSetting: boolean;
    generalError: unknown;
    crossSellingError: unknown;
    translate: (key: string) => string;
    navigate: (route: TabRoute) => void;
};

/**
 * The tabs of a product in the order the page shows them.
 * General and specifications always show; the advanced mode adds the rest, and the variants tab only for a product that is no variant itself.
 *
 * @example
 * createProductDetailTabs({ productId, isChild: false, showModeSetting: true, generalError, crossSellingError, translate: this.$t, navigate: this.$router.push });
 *
 * @private
 */
export function createProductDetailTabs(input: ProductDetailTabsInput): ProductDetailTab[] {
    const createRouteTab = (label: string, routeName: string, additionalProperties = {}): ProductDetailTab => {
        const route = { name: routeName, params: { id: input.productId } };

        return {
            label: input.translate(label),
            name: route.name,
            onClick: () => {
                input.navigate(route);
            },
            ...additionalProperties,
        };
    };

    const tabs = [
        createRouteTab('sw-product.detail.tabGeneral', 'sw.product.detail.base', { hasError: input.generalError }),
        createRouteTab('sw-product.detail.tabSpecifications', 'sw.product.detail.specifications'),
    ];

    if (input.showModeSetting) {
        tabs.push(createRouteTab('sw-product.detail.tabAdvancedPrices', 'sw.product.detail.prices'));
    }

    if (!input.isChild && input.showModeSetting) {
        tabs.push(createRouteTab('sw-product.detail.tabVariation', 'sw.product.detail.variants'));
    }

    if (input.showModeSetting) {
        tabs.push(
            createRouteTab('sw-product.detail.tabLayout', 'sw.product.detail.layout'),
            createRouteTab('sw-product.detail.tabSeo', 'sw.product.detail.seo'),
            createRouteTab('sw-product.detail.tabCrossSelling', 'sw.product.detail.crossSelling', {
                hasError: input.crossSellingError,
            }),
            createRouteTab('sw-product.detail.tabReviews', 'sw.product.detail.reviews'),
        );
    }

    return tabs;
}
