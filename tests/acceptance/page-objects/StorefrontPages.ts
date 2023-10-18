import { test as base } from '@playwright/test';
import { SetupFixtures } from '@fixtures/SetupFixtures';
import { ProductDetailPage } from './Storefront/ProductDetail';
import { CheckoutCartPage } from './Storefront/CheckoutCart';
import { CheckoutConfirmPage } from './Storefront/CheckoutConfirm';
import { CheckoutFinishPage } from './Storefront/CheckoutFinish';

export { ProductDetailPage, CheckoutCartPage, CheckoutConfirmPage, CheckoutFinishPage };

export interface StorefrontPages {
    productDetailPage: ProductDetailPage,
    checkoutCartPage: CheckoutCartPage,
    checkoutConfirmPage: CheckoutConfirmPage,
    checkoutFinishPage: CheckoutFinishPage,
}

export const test = base.extend<StorefrontPages, SetupFixtures>({
    productDetailPage: async ({ storefrontPage, salesChannelProduct }, use) => {
        await use(new ProductDetailPage(storefrontPage, salesChannelProduct));
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
