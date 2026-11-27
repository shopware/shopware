import { test } from '@fixtures/AcceptanceTest';

test(
    'Keyboard users can skip the listing filters to the products.',
    { tag: ['@Accessibility', '@Storefront'] },
    async ({ ShopCustomer, TestDataService, StorefrontCategory, StorefrontSearch, IdProvider }) => {
        const productName = `Skip ${IdProvider.getIdPair().uuid}`;
        const product = await TestDataService.createBasicProduct({ name: productName });
        const category = await TestDataService.createCategory();
        await TestDataService.assignProductCategory(product.id, category.id);

        const layoutWithoutFilter = await TestDataService.createBasicPageLayout('product_list');
        const categoryWithoutFilter = await TestDataService.createCategory({ cmsPageId: layoutWithoutFilter.id });
        await TestDataService.assignProductCategory(product.id, categoryWithoutFilter.id);

        await TestDataService.clearCaches();

        const page = StorefrontCategory.page;
        const skipLink = page.locator('.skip-to-content a[href="#product-listing"]');

        await test.step('The category page with filters offers a link that moves to the products', async () => {
            await ShopCustomer.goesTo(StorefrontCategory.url(category.name));
            await ShopCustomer.expects(page.locator('.cms-element-sidebar-filter')).toBeVisible();
            await ShopCustomer.expects(page.locator('#product-listing')).toHaveCount(1);
            await ShopCustomer.expects(skipLink).toHaveCount(1);

            await skipLink.focus();
            await ShopCustomer.expects(skipLink).toBeVisible();
            await ShopCustomer.expects(skipLink).toHaveText('Skip to products');

            await skipLink.press('Enter');
            await ShopCustomer.expects(page).toHaveURL(/#product-listing$/);
        });

        await test.step('The link is hidden below the lg breakpoint, where the filters collapse', async () => {
            await page.setViewportSize({ width: 768, height: 1024 });
            await ShopCustomer.goesTo(StorefrontCategory.url(category.name));
            await ShopCustomer.expects(skipLink).toBeHidden();
            await page.setViewportSize({ width: 1920, height: 1080 });
        });

        await test.step('A category layout without a sidebar filter has no link', async () => {
            await ShopCustomer.goesTo(StorefrontCategory.url(categoryWithoutFilter.name));
            await ShopCustomer.expects(skipLink).toHaveCount(0);
        });

        await test.step('The search page with results offers the link', async () => {
            await ShopCustomer.goesTo(StorefrontSearch.url(productName));
            await ShopCustomer.expects(page.locator('#product-listing')).toHaveCount(1);
            await ShopCustomer.expects(skipLink).toHaveCount(1);
        });

        await test.step('The search page without results has no link', async () => {
            await ShopCustomer.goesTo(StorefrontSearch.url(`NoResult${IdProvider.getIdPair().id}`));
            await ShopCustomer.expects(skipLink).toHaveCount(0);
        });
    },
);
