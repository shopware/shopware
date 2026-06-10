import { expect } from '@playwright/test';
import { test } from '@fixtures/AcceptanceTest';

const viewports = [
    { name: 'mobile S', width: 375, height: 667 },
    { name: 'mobile M', width: 390, height: 844 },
    { name: 'mobile X', width: 414, height: 896 },
    { name: 'mobile XL', width: 430, height: 932 },
    { name: 'mobile breakpoint', width: 500, height: 844 },
    { name: 'above mobile breakpoint', width: 501, height: 844 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1280, height: 720 },
] as const;

test.describe('Administration: smart bar actions', () => {
    viewports.forEach((viewport) => {
        test(`are reachable on ${viewport.name} viewport`, async ({
            ShopAdmin,
            TestDataService,
            AdminManufacturerDetail,
        }) => {
            const manufacturer = await TestDataService.createBasicManufacturer();

            await AdminManufacturerDetail.page.setViewportSize({
                width: viewport.width,
                height: viewport.height,
            });
            await ShopAdmin.goesTo(AdminManufacturerDetail.url(manufacturer.id));

            await expect(AdminManufacturerDetail.cancelButton).toBeVisible();
            await expect(AdminManufacturerDetail.saveButton).toBeVisible();

            await expect(AdminManufacturerDetail.cancelButton).toBeInViewport({ ratio: 1 });
            await expect(AdminManufacturerDetail.saveButton).toBeInViewport({ ratio: 1 });
        });
    });
});
