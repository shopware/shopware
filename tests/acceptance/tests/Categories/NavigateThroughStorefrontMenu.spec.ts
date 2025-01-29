import { test } from '@fixtures/AcceptanceTest';

test(
    'As a customer, I want breadcrumb to update when I select a category to understand my location on the site.',
    { tag: '@Categories' },
    async ({ ShopCustomer, StorefrontHome, TestDataService, InstanceMeta }) => {
        test.skip(InstanceMeta.features['V6_7_0_0'], 'Blocked by https://shopware.atlassian.net/browse/NEXT-40154');

        const category1 = await TestDataService.createCategory({ type: 'folder' });
        const category2 = await TestDataService.createCategory({ type: 'page' });
        const category3 = await TestDataService.createCategory({ type: 'link' });
        const subCategory1 = await TestDataService.createCategory({ parentId: category1.id });
        const subCategory2 = await TestDataService.createCategory({ parentId: category2.id });
        const subCategory3 = await TestDataService.createCategory({ parentId: category3.id });

        await test.step('Verify if folder category has a sub category and the folder category in breadcrumb is a div element.', async () => {
            const mainCategoryLocators = await StorefrontHome.getMenuItemByCategoryName(category1.name);
            const subCategoryLocators = await StorefrontHome.getMenuItemByCategoryName(subCategory1.name);

            await ShopCustomer.goesTo(StorefrontHome.url());
            await ShopCustomer.expects(mainCategoryLocators.menuNavigationItem).toHaveText(category1.name);
            await ShopCustomer.expects(mainCategoryLocators.offcanvasNavigationItem).toHaveText(category1.name);

            await mainCategoryLocators.menuNavigationItem.hover();
            await ShopCustomer.expects(mainCategoryLocators.flyoutCategoryLink).not.toBeVisible();

            await subCategoryLocators.menuNavigationItem.click();

            await ShopCustomer.expects(mainCategoryLocators.breadcrumbNavigationItem).toHaveText(category1.name);
            await ShopCustomer.expects(mainCategoryLocators.breadcrumbNavigationLinkItem).not.toBeVisible();
            const breadcrumbNavigationItemTagName = await mainCategoryLocators.breadcrumbNavigationItem.evaluate((el) =>
                el.tagName.toLowerCase()
            );
            await ShopCustomer.expects(breadcrumbNavigationItemTagName).toBe('div');
        });

        await test.step('Verify if page category has a sub category and the page category in breadcrumb is a link element.', async () => {
            const mainCategoryLocators = await StorefrontHome.getMenuItemByCategoryName(category2.name);
            const subCategoryLocators = await StorefrontHome.getMenuItemByCategoryName(subCategory2.name);

            await ShopCustomer.goesTo(StorefrontHome.url());

            await ShopCustomer.expects(mainCategoryLocators.menuNavigationItem).toHaveText(category2.name);
            await ShopCustomer.expects(mainCategoryLocators.offcanvasNavigationItem).toHaveText(category2.name);

            await mainCategoryLocators.menuNavigationItem.hover();
            await ShopCustomer.expects(mainCategoryLocators.flyoutCategoryLink).toBeVisible();

            await subCategoryLocators.menuNavigationItem.click();

            await ShopCustomer.expects(mainCategoryLocators.breadcrumbNavigationItem).toHaveText(category2.name);
            await ShopCustomer.expects(mainCategoryLocators.breadcrumbNavigationLinkItem).toBeVisible();
            const breadcrumbNavigationItemTagName = await mainCategoryLocators.breadcrumbNavigationItem.evaluate((el) =>
                el.tagName.toLowerCase()
            );
            await ShopCustomer.expects(breadcrumbNavigationItemTagName).toBe('span');
        });

        await test.step('Verify if link category has a sub category and the link category in breadcrumb is a link element.', async () => {
            const mainCategoryLocators = await StorefrontHome.getMenuItemByCategoryName(category3.name);
            const subCategoryLocators = await StorefrontHome.getMenuItemByCategoryName(subCategory3.name);

            await ShopCustomer.goesTo(StorefrontHome.url());

            await ShopCustomer.expects(mainCategoryLocators.menuNavigationItem).toHaveText(category3.name);
            await ShopCustomer.expects(mainCategoryLocators.offcanvasNavigationItem).toHaveText(category3.name);

            await mainCategoryLocators.menuNavigationItem.hover();
            await ShopCustomer.expects(mainCategoryLocators.flyoutCategoryLink).not.toBeVisible();

            await subCategoryLocators.menuNavigationItem.click();

            await ShopCustomer.expects(mainCategoryLocators.breadcrumbNavigationItem).toHaveText(category3.name);
            await ShopCustomer.expects(mainCategoryLocators.breadcrumbNavigationLinkItem).toBeVisible();
            const breadcrumbNavigationItemTagName = await mainCategoryLocators.breadcrumbNavigationItem.evaluate((el) =>
                el.tagName.toLowerCase()
            );
            await ShopCustomer.expects(breadcrumbNavigationItemTagName).toBe('span');
        });
    }
);
