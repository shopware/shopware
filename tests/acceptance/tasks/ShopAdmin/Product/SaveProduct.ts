import { expect, test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';


export const SaveProduct = base.extend<{ SaveProduct: Task }, FixtureTypes>({
    SaveProduct: async ({ shopAdmin, adminProductDetailPage }, use ) => {
        const task = () => {
            return async function SaveProduct() {

                await adminProductDetailPage.savePhysicalProductButton.click();

                //Wait until product is saved via API
                const response = await adminProductDetailPage.page.waitForResponse(`${process.env['APP_URL']}api/_action/sync`);

                //Assertions
                await expect(response.ok()).toBeTruthy();
                await shopAdmin.expects(adminProductDetailPage.savePhysicalProductButton).toBeVisible();
                await shopAdmin.expects(adminProductDetailPage.savePhysicalProductButton).toContainText('Save');
                await shopAdmin.expects(adminProductDetailPage.saveButtonCheckMark).toBeHidden();
                await shopAdmin.expects(adminProductDetailPage.saveButtonLoadingSpinner).toBeHidden();
                await shopAdmin.expects(adminProductDetailPage.page.getByText('The following error occurred:')).toBeHidden();
            }
        }
        await use(task);
    },
});
