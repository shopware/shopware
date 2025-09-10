import { test, expect } from '@fixtures/AcceptanceTest';
import { setViewport } from '@shopware-ag/acceptance-test-suite';

test('Visual: Customer Detail Page', { tag: '@Visual' }, async ({ 
    ShopAdmin,
    TestDataService,
    AdminCustomerListing,
    AdminCustomerDetail,
    DefaultSalesChannel,
}) => {

    await test.step('Creates a screenshot of the customer listing page in its empty state.', async () => {
        await TestDataService.AdminApiClient.delete(`./customer/${DefaultSalesChannel.customer.id}`);
        await ShopAdmin.goesTo(AdminCustomerListing.url());
        await setViewport(AdminCustomerListing.page, {
            width: 1440,
            contentHeight: 1200,
        });
       await expect(AdminCustomerListing.page.locator('.sw-desktop__content')).toHaveScreenshot('Customer-Listing-Empty-State.png');
    });

    const countryId = await DefaultSalesChannel.salesChannel.countryId;
    const salutationId = await DefaultSalesChannel.salesChannel.salutationId;
    const customer = await TestDataService.createCustomer({
            firstName: 'John',
            lastName: 'Goldblum',
            customerNumber: '12345',
            email: 'john.goldblum@example.com',
            createdAt: '2025-09-04T06:36:38.101+00:00',
            defaultShippingAddress: {
                firstName: 'John',
                lastName: 'Doe',
                city: 'Schöppingen',
                street: 'Ebbinghoff 10',
                zipcode: '48624',
                countryId: countryId,
                salutationId: salutationId,
            },
            defaultBillingAddress: {
                firstName: 'John',
                lastName: 'Doe',
                city: 'Schöppingen',
                street: 'Ebbinghoff 10',
                zipcode: '48624',
                countryId: countryId,
                salutationId: salutationId,
            },
    });

    await test.step('Creates a screenshot of the customer from Customer Listing page with customer.', async () => {   
        await ShopAdmin.goesTo(AdminCustomerListing.url());
        await setViewport(AdminCustomerListing.page, {
            width: 1440,
            contentHeight: 1200,
        });
        await expect(AdminCustomerListing.page.locator('.sw-desktop__content')).toHaveScreenshot('Customer-Listing-With-Customer.png');
    }); 

    await test.step('Creates a screenshot of the customer from Customer Detail General Tab.', async () => {   
        await ShopAdmin.goesTo((AdminCustomerDetail.url(customer.id)));
        await setViewport(AdminCustomerDetail.page, {
            width: 1200,
            contentHeight: 1400,
        });
        await expect(AdminCustomerDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Customer-Detail-General-Tab.png');
    }); 

    await test.step('Creates a screenshot of the customer from Customer Detail Adresses Tab.', async () => {   
       await AdminCustomerDetail.addressesTab.click();
        await setViewport(AdminCustomerDetail.page, {
            width: 1200,
            contentHeight: 600,
        });
        await expect(AdminCustomerDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Customer-Detail-Adresses-Tab.png');
    }); 

    await test.step('Creates a screenshot of the customer from Customer Detail Orders Tab.', async () => {
        await AdminCustomerDetail.ordersTab.click();
        await setViewport(AdminCustomerDetail.page, {
            width: 1200,
            contentHeight: 700,
        });
        await expect(AdminCustomerDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Customer-Detail-Orders-Tab.png');
    }); 
});
