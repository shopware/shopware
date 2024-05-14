import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const GenerateVariants = base.extend<{ GenerateVariants: Task }, FixtureTypes>({
    GenerateVariants: async ({ shopAdmin, adminProductDetailPage }, use)=> {
        const task = () => {
            return async function GenerateVariants() {
                // Navigate to variants tab
                await adminProductDetailPage.variantsTabLink.click();
                await adminProductDetailPage.generateVariantsButton.click();
                await shopAdmin.expects(adminProductDetailPage.variantsModalHeadline).toBeVisible();
                await shopAdmin.expects(adminProductDetailPage.propertyGroupColor).toBeVisible();

                // Select color properties
                await adminProductDetailPage.propertyGroupColor.click();

                await adminProductDetailPage.propertyOptionColorBlue.check();
                await shopAdmin.expects(adminProductDetailPage.propertyOptionColorBlue).toBeChecked();

                await adminProductDetailPage.propertyOptionColorRed.check();
                await shopAdmin.expects(adminProductDetailPage.propertyOptionColorRed).toBeChecked();

                // Select size properties
                await adminProductDetailPage.propertyGroupSize.click();

                await adminProductDetailPage.propertyOptionSizeMedium.check();
                await shopAdmin.expects(adminProductDetailPage.propertyOptionSizeMedium).toBeChecked();

                await adminProductDetailPage.propertyOptionSizeLarge.check();
                await shopAdmin.expects(adminProductDetailPage.propertyOptionSizeLarge).toBeChecked();

                // Proceed to generate variants
                await adminProductDetailPage.variantsNextButton.click();
                await shopAdmin.expects(adminProductDetailPage.page.getByText('4 variants will be added, 0 variants will be deleted.')).toBeVisible();

                // Save variants
                await adminProductDetailPage.variantsSaveButton.click();
                await shopAdmin.expects(adminProductDetailPage.variantsModal).not.toBeVisible();
            }
        };

        await use(task);
    },
});
