import { test } from '@fixtures/AcceptanceTest';

test(
    'As a customer, I want to see a reduced header and footer during checkout.', { tag: ['@Checkout'] },
    async ({
        ShopCustomer,
        TestDataService,
        StorefrontProductDetail,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        StorefrontSearchSuggest,
        StorefrontHeader,
        StorefrontFooter,
    }) => {
        const basicProduct = await TestDataService.createBasicProduct();
        await ShopCustomer.attemptsTo(Login());

        await test.step('Validate that the full header and footer are visible on the product detail page.', async () => {
            await ShopCustomer.goesTo(StorefrontProductDetail.url(basicProduct));
            await ShopCustomer.expects(StorefrontSearchSuggest.searchInput).toBeVisible();
            await ShopCustomer.expects(StorefrontHeader.mainNavigationLink).toBeVisible();
            await ShopCustomer.expects(StorefrontFooter.footerHeadline).toBeVisible();
            await ShopCustomer.expects(StorefrontFooter.footerContent).toBeVisible();
            await ShopCustomer.expects(StorefrontFooter.footerHotline).toBeVisible();
            await ShopCustomer.expects(StorefrontFooter.footerContactForm).toBeVisible();
        });
        
        await test.step('Validate that the full header and footer are not visible on the checkout page.', async () => {
            await ShopCustomer.attemptsTo(AddProductToCart(basicProduct));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());        
            await ShopCustomer.expects(StorefrontSearchSuggest.searchInput).not.toBeVisible();
            await ShopCustomer.expects(StorefrontHeader.mainNavigationLink).not.toBeVisible();
            await ShopCustomer.expects(StorefrontFooter.footerHeadline).not.toBeVisible();
            await ShopCustomer.expects(StorefrontFooter.footerContent).not.toBeVisible();
            await ShopCustomer.expects(StorefrontFooter.footerHotline).not.toBeVisible();
            await ShopCustomer.expects(StorefrontFooter.footerContactForm).not.toBeVisible();
        });
    }
);