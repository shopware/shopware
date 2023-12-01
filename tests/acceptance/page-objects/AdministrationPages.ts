import { test as base } from 'playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import { AdminProductDetailPage } from './Administration/ProductDetail';

export interface AdministrationPages {
    adminProductDetailPage: AdminProductDetailPage,
}

export const test = base.extend<FixtureTypes>({
    adminProductDetailPage: async ({ adminPage, productData }, use) => {
        await use(new AdminProductDetailPage(adminPage, productData));
    },
});
