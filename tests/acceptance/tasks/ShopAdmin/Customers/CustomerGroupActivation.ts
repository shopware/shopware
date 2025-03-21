import { test as base, expect } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const CustomerGroupActivation = base.extend<{ CustomerGroupActivation: Task }, FixtureTypes>({
    CustomerGroupActivation: async ({ ShopAdmin, AdminCustomerListing, AdminCustomerDetail }, use) => {
        const task = (email: string, customerGroupName: string) => {

            return async function CustomerGroupActivation() {
                await ShopAdmin.goesTo(AdminCustomerListing.url());
                const customerLineItem = await AdminCustomerListing.getCustomerByEmail(email);
                await ShopAdmin.expects(customerLineItem.customerGroup).toBeVisible({ timeout: 10000 });
                await customerLineItem.customerName.click();
                const customerGroupAlert = await AdminCustomerDetail.getCustomerGroupAlert(customerGroupName);
                await ShopAdmin.expects(customerGroupAlert).toContainText(customerGroupName);
                await ShopAdmin.expects(AdminCustomerDetail.customerGroupRequestMessage).toBeVisible();
                const responsePromise = AdminCustomerDetail.page.waitForResponse('**/api/_action/customer-group-registration/accept');
                await AdminCustomerDetail.customerGroupAcceptButton.click();
                const customerGroupResponse = await responsePromise;
                expect(customerGroupResponse).toBeTruthy();
            }
        };

        await use(task);
    },
});
