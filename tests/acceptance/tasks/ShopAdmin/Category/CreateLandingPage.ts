import { test as base, expect } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const CreateLandingPage = base.extend<{ CreateLandingPage: Task }, FixtureTypes>({
    CreateLandingPage: async ({ ShopAdmin, Categories, LandingPageDetail, IdProvider }, use ) => {

        const landingPageData = {
            name: `Landing Page ${IdProvider.getIdPair().uuid}`,
            salesChannel: 'Storefront',
            seoUrl: `landing-${IdProvider.getIdPair().id}`,
        };
        const task = (layoutName: string, status: boolean) => {
            return async function CreateLandingPage() {

                await Categories.landingPageHeadline.click();
                await Categories.addLandingPageButton.click();

                await ShopAdmin.expects(LandingPageDetail.saveLandingPageButton).toBeVisible();
                await ShopAdmin.expects(LandingPageDetail.saveLandingPageButton).toContainText('Save');

                //Fill details and save
                await LandingPageDetail.nameInput.fill(landingPageData.name);
                await LandingPageDetail.landingPageStatus.setChecked(status);
                await LandingPageDetail.salesChannelSelect.click();
                await LandingPageDetail.salesChannelItem.waitFor({ state: 'visible' });
                await LandingPageDetail.salesChannelItem.click();
                await LandingPageDetail.seoUrlInput.fill(landingPageData.seoUrl);

                if (layoutName) {
                    await LandingPageDetail.layoutTab.click();
                    // Verify empty layout state
                    await ShopAdmin.expects(LandingPageDetail.layoutEmptyState).toBeVisible();
                    await ShopAdmin.expects(LandingPageDetail.assignCreateNewLayoutButton).toBeVisible();
                    // Select existing layout
                    await LandingPageDetail.assignLayoutButton.click();
                    await LandingPageDetail.loadingSpinner.waitFor({ state: 'hidden' });
                    await LandingPageDetail.searchLayoutInput.dblclick();
                    // Search input need to delay press more than 300ms to mimic user typing in order to activate search action
                    await LandingPageDetail.searchLayoutInput.pressSequentially(layoutName.split(' ')[1].substring(0,5), {delay: 500});
                    await LandingPageDetail.layoutItem.first().waitFor({ state: 'visible' });
                    await LandingPageDetail.layoutItem.first().click();
                    await LandingPageDetail.layoutSaveButton.click();
                }
                await LandingPageDetail.saveLandingPageButton.click();

                // Wait until landing page is saved via API
                await LandingPageDetail.page.waitForResponse(`${process.env['APP_URL']}api/search/landing-page`);

                // Verify created landing page
                const createdLandingPage = Categories.landingPageItems.locator(`text="${landingPageData.name}"`);
                await createdLandingPage.click();
                await LandingPageDetail.loadingSpinner.waitFor({ state: 'hidden' });

                // Verify general tab detail
                await ShopAdmin.expects(LandingPageDetail.nameInput).toHaveValue(landingPageData.name);
                await ShopAdmin.expects(LandingPageDetail.landingPageStatus).toBeChecked();
                await ShopAdmin.expects(LandingPageDetail.salesChannelSelect).toHaveText(landingPageData.salesChannel);
                await ShopAdmin.expects(LandingPageDetail.seoUrlInput).toHaveValue(landingPageData.seoUrl);
                // Verify layout tab detail
                if (layoutName) {
                    await LandingPageDetail.layoutTab.click();
                    await ShopAdmin.expects(LandingPageDetail.layoutAssignmentCardTitle).toHaveText(layoutName);
                    await ShopAdmin.expects(LandingPageDetail.layoutAssignmentCardHeadline).toHaveText(layoutName);

                    await ShopAdmin.expects(LandingPageDetail.layoutAssignmentContentSection).toBeVisible();
                    await ShopAdmin.expects(LandingPageDetail.layoutResetButton).toBeVisible();
                    await ShopAdmin.expects(LandingPageDetail.changeLayoutButton).toBeVisible();
                    await ShopAdmin.expects(LandingPageDetail.editInDesignerButton).toBeVisible();
                }
            }
        }

        await use(task);
    },
});
