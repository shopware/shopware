import { test } from '@fixtures/AcceptanceTest';
import { expect } from '@playwright/test';

test('Shop administrator should be able to create a internal link type of category.', { tag: '@Categories' }, async ({
     ShopAdmin,
     IdProvider,
     AdminCategories,
     CreateLinkTypeCategory,
     TestDataService,
}) => {

    const categoryData = {
        name: `00_category_link_${IdProvider.getIdPair().uuid}`,
        categoryType: 'Link',
        status: true,
    };

    const categoryCustomizableLinkData = {
        linkType: 'Internal',
        entity: 'Category',
        category: `00_category_${IdProvider.getIdPair().uuid}`,
        openInNewTab: true,
    };

    await test.step('Create a category with internal link type of Category', async () => {
        await TestDataService.createCategory({ name: categoryCustomizableLinkData.category, active: true, parentId: null });
        await ShopAdmin.goesTo(AdminCategories.url());
        await ShopAdmin.attemptsTo(CreateLinkTypeCategory(categoryData, categoryCustomizableLinkData, categoryCustomizableLinkData.category));

        // Verify general data
        await AdminCategories.categoryItems.filter({ hasText: categoryData.name }).click();
        await expect(AdminCategories.nameInput).toHaveValue(categoryData.name);
        await expect(AdminCategories.activeCheckbox).toBeChecked({ checked: categoryData.status });
        // Verify category customisable link data
        await expect(AdminCategories.linkTypeSelectionList).toHaveText(categoryCustomizableLinkData.linkType);
        await expect(AdminCategories.entitySelectionList).toHaveText(categoryCustomizableLinkData.entity);
        await expect(AdminCategories.categorySelectionList).toHaveText(new RegExp(`${categoryCustomizableLinkData.entity}\\s+${categoryCustomizableLinkData.category}`));
        await expect(AdminCategories.openInNewTabCheckbox).toBeChecked({ checked: categoryCustomizableLinkData.openInNewTab });
    });

});

test('Shop administrator should be able to create a internal link type of product.', { tag: '@Categories' }, async ({
    ShopAdmin,
    IdProvider,
    AdminCategories,
    CreateLinkTypeCategory,
    TestDataService,
}) => {

    const product = await TestDataService.createBasicProduct();
    const categoryData = {
        name: `00_product_link_${IdProvider.getIdPair().uuid}`,
        categoryType: 'Link',
        status: true,
    };

    const categoryCustomizableLinkData = {
        linkType: 'Internal',
        entity: 'Product',
        product: product.name,
        category: `00_category_${IdProvider.getIdPair().uuid}`,
        openInNewTab: true,
    };

    await test.step('Create a category with internal link type of Product', async () => {
        await TestDataService.createCategory({ name: categoryCustomizableLinkData.category, active: true, parentId: null });
        await ShopAdmin.goesTo(AdminCategories.url());
        await ShopAdmin.attemptsTo(CreateLinkTypeCategory(categoryData, categoryCustomizableLinkData, categoryCustomizableLinkData.category));

        // Verify general data
        await AdminCategories.categoryItems.filter({ hasText: categoryData.name }).click();
        await expect(AdminCategories.nameInput).toHaveValue(categoryData.name);
        await expect(AdminCategories.activeCheckbox).toBeChecked({ checked: categoryData.status });
        // Verify category customisable link data
        await expect(AdminCategories.linkTypeSelectionList).toHaveText(categoryCustomizableLinkData.linkType);
        await expect(AdminCategories.entitySelectionList).toHaveText(categoryCustomizableLinkData.entity);
        await expect(AdminCategories.productSelectionList).toHaveText(new RegExp(`${categoryCustomizableLinkData.entity}\\s+${categoryCustomizableLinkData.product}`));
        await expect(AdminCategories.openInNewTabCheckbox).toBeChecked({ checked: categoryCustomizableLinkData.openInNewTab });
    });

});

test('Shop administrator should be able to create a internal link type of landing page.', { tag: '@Categories' }, async ({
     ShopAdmin,
     IdProvider,
     AdminCategories,
     CreateLinkTypeCategory,
     CreateLandingPage,
     TestDataService,
}) => {

    const landingPageData = {
        name: `landing_page_${IdProvider.getIdPair().uuid}`,
        status: true,
        salesChannel: 'Storefront',
        seoUrl: `landing-page-${IdProvider.getIdPair().uuid}`,
    };

    const categoryData = {
        name: `00_landing_page_${IdProvider.getIdPair().uuid}`,
        categoryType: 'Link',
        status: true,
    };

    const categoryCustomizableLinkData = {
        linkType: 'Internal',
        entity: 'Landing page',
        landingPage: landingPageData.name,
        category: `00_category_${IdProvider.getIdPair().uuid}`,
        openInNewTab: true,
    };

    await test.step('Create a landing page', async () => {
        await ShopAdmin.goesTo(AdminCategories.url());
        await ShopAdmin.attemptsTo(CreateLandingPage(null, landingPageData));
    });

    await test.step('Create a category with internal link type of Product', async () => {
        await TestDataService.createCategory({ name: categoryCustomizableLinkData.category, active: true, parentId: null });
        await ShopAdmin.goesTo(AdminCategories.url());
        await ShopAdmin.attemptsTo(CreateLinkTypeCategory(categoryData, categoryCustomizableLinkData, categoryCustomizableLinkData.category));

        // Verify general data
        await AdminCategories.categoryItems.filter({ hasText: categoryData.name }).click();
        await expect(AdminCategories.nameInput).toHaveValue(categoryData.name);
        await expect(AdminCategories.activeCheckbox).toBeChecked({ checked: categoryData.status });
        // Verify category customisable link data
        await expect(AdminCategories.linkTypeSelectionList).toHaveText(categoryCustomizableLinkData.linkType);
        await expect(AdminCategories.entitySelectionList).toHaveText(categoryCustomizableLinkData.entity);
        await expect(AdminCategories.landingPageSelectionList).toHaveText(new RegExp(`${categoryCustomizableLinkData.entity}\\s+${categoryCustomizableLinkData.landingPage}`));
        await expect(AdminCategories.openInNewTabCheckbox).toBeChecked({ checked: categoryCustomizableLinkData.openInNewTab });
    });

});
