import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const CreateLandingPage = base.extend<{ CreateLandingPage: Task }, FixtureTypes>({
    CreateLandingPage: async ({ ShopAdmin, AdminCategories, AdminLandingPageCreate, AdminLandingPageDetail, TestDataService }, use ) => {

        const task = (layoutName: string, landingPageData) => {
            return async function CreateLandingPage() {
                await AdminCategories.landingPageHeadline.click();
                await AdminCategories.addLandingPageButton.click();

                await ShopAdmin.expects(AdminLandingPageDetail.saveLandingPageButton).toBeVisible();
                await ShopAdmin.expects(AdminLandingPageDetail.saveLandingPageButton).toContainText('Save');

                //Fill details and save
                await AdminLandingPageCreate.nameInput.fill(landingPageData.name);
                await AdminLandingPageCreate.landingPageStatus.setChecked(landingPageData.status);
                await AdminLandingPageCreate.salesChannelSelectionList.click();
                await AdminLandingPageCreate.filtersResultPopoverItemList.filter({ hasText: landingPageData.salesChannel }).click();
                await AdminLandingPageCreate.seoUrlInput.fill(landingPageData.seoUrl);

                if (layoutName) {
                    await AdminLandingPageCreate.layoutTab.click();
                    // Verify empty layout state
                    await ShopAdmin.expects(AdminLandingPageCreate.layoutEmptyState).toBeVisible();
                    await ShopAdmin.expects(AdminLandingPageCreate.createNewLayoutButton).toBeVisible();
                    // Select existing layout
                    await AdminLandingPageCreate.assignLayoutButton.click();
                    // Search input need to delay press more than 300ms to mimic user typing in order to activate search action
                    await AdminLandingPageCreate.searchLayoutInput.pressSequentially(layoutName.substring(0,5), {delay: 500});
                    await AdminLandingPageCreate.page.getByTitle(layoutName).click();
                    await AdminLandingPageCreate.layoutSaveButton.click();
                }
                await AdminLandingPageCreate.saveLandingPageButton.click();
                await AdminLandingPageCreate.loadingSpinner.waitFor({ state: 'hidden' });
                const url = AdminLandingPageDetail.page.url();
                const landingPageId = url.split('/')[url.split('/').length - 2];
                TestDataService.addCreatedRecord('landing_page', landingPageId);
            }
        }

        await use(task);
    },
});
