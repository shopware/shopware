import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import { ProductDetailPage } from './Storefront/ProductDetail';
import { CheckoutCartPage } from './Storefront/CheckoutCart';
import { CheckoutConfirmPage } from './Storefront/CheckoutConfirm';
import { CheckoutFinishPage } from './Storefront/CheckoutFinish';

export interface StorefrontPages {
    productDetailPage: ProductDetailPage,
    checkoutCartPage: CheckoutCartPage,
    checkoutConfirmPage: CheckoutConfirmPage,
    checkoutFinishPage: CheckoutFinishPage,
}

export const test = base.extend<FixtureTypes>({
    productDetailPage: async ({ storefrontPage, productData }, use) => {
        await use(new ProductDetailPage(storefrontPage, productData));
    },

    checkoutCartPage: async ({ storefrontPage }, use) => {
        await use(new CheckoutCartPage(storefrontPage));
    },

    checkoutConfirmPage: async ({ storefrontPage }, use) => {
        await use(new CheckoutConfirmPage(storefrontPage));
    },

    checkoutFinishPage: async ({ storefrontPage }, use) => {
        await use(new CheckoutFinishPage(storefrontPage));
    },
});
