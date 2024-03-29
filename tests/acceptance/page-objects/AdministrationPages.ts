import { test as base } from 'playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import { AdminProductDetailPage } from './Administration/ProductDetail';
import { AdminOrderDetailPage } from './Administration/OrderDetail';

export interface AdministrationPages {
    adminProductDetailPage: AdminProductDetailPage,
    adminOrderDetailPage: AdminProductDetailPage,
}

export const test = base.extend<FixtureTypes>({
    adminProductDetailPage: async ({ adminPage, productData }, use) => {
        await use(new AdminProductDetailPage(adminPage, productData));
    },

    adminOrderDetailPage: async ({ adminPage, orderData }, use) => {
        await use(new AdminOrderDetailPage(adminPage, orderData));
    },
});
