import type { ProductDetailTabsInput } from '../private';
import { createProductDetailTabs } from '../private';

/**
 * @sw-package inventory
 */

/**
 * Where the tabs navigated to, in order.
 *
 * @private
 */
export const navigations: unknown[] = [];

/**
 * The tabs of a parent product in advanced mode without errors, with the given changes.
 * The labels are the snippet keys, and every navigation lands in `navigations`.
 *
 * @private
 */
export function getTabs(changes: Partial<ProductDetailTabsInput> = {}) {
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
