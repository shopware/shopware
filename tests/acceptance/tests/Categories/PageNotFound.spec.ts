import { test } from '@fixtures/AcceptanceTest';

test(
    'As a customer, I want to see 404 layout when I navigate to a non-existing page.',
    { tag: '@Categories' },
    async ({ ShopCustomer, StorefrontPageNotFound, StorefrontHome }) => {
        await ShopCustomer.goesTo(StorefrontPageNotFound.url());

        await ShopCustomer.expects(StorefrontPageNotFound.pageNotFoundImage).toBeVisible();
        await ShopCustomer.expects(StorefrontPageNotFound.headline).toHaveText('Page not found');
        await ShopCustomer.expects(StorefrontPageNotFound.pageNotFoundMessage).toHaveText(
            `We are sorry, the page you're looking for could not be found. It may no longer exist or may have been moved.`
        );
        await StorefrontPageNotFound.backToShopButton.click();
        await ShopCustomer.expects(StorefrontHome.mainNavigationLink).toContainText('Home');
    }
);
