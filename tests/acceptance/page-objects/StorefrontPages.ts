import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import { ProductDetailPage } from './Storefront/ProductDetail';
import { AccountPage } from './Storefront/Account';
import { AccountLoginPage } from './Storefront/AccountLogin';
import { CheckoutCartPage } from './Storefront/CheckoutCart';
import { CheckoutConfirmPage } from './Storefront/CheckoutConfirm';
import { CheckoutFinishPage } from './Storefront/CheckoutFinish';

export interface StorefrontPages {
    productDetailPage: ProductDetailPage,
    accountPage: AccountPage,
    accountLoginPage: AccountLoginPage,
    checkoutCartPage: CheckoutCartPage,
    checkoutConfirmPage: CheckoutConfirmPage,
    checkoutFinishPage: CheckoutFinishPage,
}

export const test = base.extend<FixtureTypes>({
    productDetailPage: async ({ storefrontPage, productData }, use) => {
        await use(new ProductDetailPage(storefrontPage, productData));
    },

    accountPage: async ({ storefrontPage }, use) => {
        await use(new AccountPage(storefrontPage));
    },

    accountLoginPage: async ({ storefrontPage }, use) => {
        await use(new AccountLoginPage(storefrontPage));
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
