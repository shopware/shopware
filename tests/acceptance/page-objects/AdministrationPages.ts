import { test as base } from 'playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import { AdminProductDetailPage } from './Administration/ProductDetail';
import { AdminOrderDetailPage } from './Administration/OrderDetail';
import { FirstRunWizardPage } from '@page-objects/Administration/Settings/FirstRunWizard';
import { AdminCustomerDetailPage } from './Administration/CustomerDetail';
import { FlowBuilderListingPage } from './Administration/FlowBuilderListing';
import { FlowBuilderCreatePage } from './Administration/FlowBuilderCreatePage';

export interface AdministrationPages {
    adminProductDetailPage: AdminProductDetailPage,
    firstRunWizardPage: FirstRunWizardPage,
    adminOrderDetailPage: AdminOrderDetailPage,
    adminCustomerDetailPage: AdminCustomerDetailPage,
    flowBuilderListingPage: FlowBuilderListingPage,
    flowBuilderCreatePage: FlowBuilderCreatePage,
}

export const test = base.extend<FixtureTypes>({
    adminProductDetailPage: async ({ adminPage, productData }, use) => {
        await use(new AdminProductDetailPage(adminPage, productData));
    },

    adminOrderDetailPage: async ({ adminPage, orderData }, use) => {
        await use(new AdminOrderDetailPage(adminPage, orderData));
    },

    firstRunWizardPage: async ({ adminPage }, use) => {
        await use(new FirstRunWizardPage(adminPage));
    },

    adminCustomerDetailPage: async ({ adminPage, defaultStorefront }, use) => {
        await use(new AdminCustomerDetailPage(adminPage, defaultStorefront.customer));
    },

    flowBuilderListingPage: async ({ adminPage }, use) => {
        await use(new FlowBuilderListingPage(adminPage));
    },

    flowBuilderCreatePage: async ({ adminPage }, use) => {
        await use(new FlowBuilderCreatePage(adminPage));
    },
});
