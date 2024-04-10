import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import { ProductDetailPage } from './Storefront/ProductDetail';
import { AccountPage } from './Storefront/Account';
import { AccountLoginPage } from './Storefront/AccountLogin';
import { CheckoutCartPage } from './Storefront/CheckoutCart';
import { CheckoutConfirmPage } from './Storefront/CheckoutConfirm';
import { CheckoutFinishPage } from './Storefront/CheckoutFinish';
import { StorefrontHomePage } from './Storefront/StorefrontHome';
import { CheckoutRegisterPage } from './Storefront/CheckoutRegister';
import { SearchPage } from './Storefront/Search';
import { SearchSuggestPage } from './Storefront/SearchSuggest';
import { AccountOrderPage } from './Storefront/AccountOrder';

export interface StorefrontPages {
    productDetailPage: ProductDetailPage,
    accountPage: AccountPage,
    accountLoginPage: AccountLoginPage,
    accountOrderPage: AccountOrderPage,
    checkoutCartPage: CheckoutCartPage,
    checkoutConfirmPage: CheckoutConfirmPage,
    checkoutFinishPage: CheckoutFinishPage,
    storefrontHomePage: StorefrontHomePage,
    checkoutRegisterPage: CheckoutRegisterPage,
    searchPage: SearchPage,
    searchSuggestPage: SearchSuggestPage,
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

    storefrontHomePage: async ({ storefrontPage }, use) => {
        await use(new StorefrontHomePage(storefrontPage));
    },

    checkoutRegisterPage: async ({ storefrontPage }, use) => {
        await use(new CheckoutRegisterPage(storefrontPage));
    },

    searchPage: async ({ storefrontPage }, use) => {
        await use(new SearchPage(storefrontPage));
    },

    searchSuggestPage: async ({ storefrontPage }, use) => {
        await use(new SearchSuggestPage(storefrontPage));
    },

    accountOrderPage: async ({ storefrontPage }, use) => {
        await use(new AccountOrderPage(storefrontPage));
    },
});
