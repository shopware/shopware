import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'As a customer, I want to see 404 layout when I navigate to a non-existing page.',
    { tag: '@Categories' },
    async ({ ShopCustomer, StorefrontPageNotFound, StorefrontHome }) => {
        await ShopCustomer.goesTo(StorefrontPageNotFound.url());

        await expect(StorefrontPageNotFound.pageNotFoundImage).toBeVisible();
        await expect(StorefrontPageNotFound.headline).toHaveText('Page not found');
        await expect(StorefrontPageNotFound.pageNotFoundMessage).toHaveText(
            `We are sorry, the page you're looking for could not be found. It may no longer exist or may have been moved.`
        );
        await StorefrontPageNotFound.backToShopButton.click();
        await expect(StorefrontHome.mainNavigationLink).toContainText('Home');
    }
);
