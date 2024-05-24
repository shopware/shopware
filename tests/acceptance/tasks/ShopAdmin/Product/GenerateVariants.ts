import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const GenerateVariants = base.extend<{ GenerateVariants: Task }, FixtureTypes>({
    GenerateVariants: async ({ ShopAdmin, AdminProductDetail }, use)=> {
        const task = () => {
            return async function GenerateVariants() {
                // Navigate to variants tab
                await AdminProductDetail.variantsTabLink.click();
                await AdminProductDetail.generateVariantsButton.click();
                await ShopAdmin.expects(AdminProductDetail.variantsModalHeadline).toBeVisible();
                await ShopAdmin.expects(AdminProductDetail.propertyGroupColor).toBeVisible();

                // Select color properties
                await AdminProductDetail.propertyGroupColor.click();

                await AdminProductDetail.propertyOptionColorBlue.check();
                await ShopAdmin.expects(AdminProductDetail.propertyOptionColorBlue).toBeChecked();

                await AdminProductDetail.propertyOptionColorRed.check();
                await ShopAdmin.expects(AdminProductDetail.propertyOptionColorRed).toBeChecked();

                // Select size properties
                await AdminProductDetail.propertyGroupSize.click();

                await AdminProductDetail.propertyOptionSizeMedium.check();
                await ShopAdmin.expects(AdminProductDetail.propertyOptionSizeMedium).toBeChecked();

                await AdminProductDetail.propertyOptionSizeLarge.check();
                await ShopAdmin.expects(AdminProductDetail.propertyOptionSizeLarge).toBeChecked();

                // Proceed to generate variants
                await AdminProductDetail.variantsNextButton.click();
                await ShopAdmin.expects(AdminProductDetail.page.getByText('4 variants will be added, 0 variants will be deleted.')).toBeVisible();

                // Save variants
                await AdminProductDetail.variantsSaveButton.click();
                await ShopAdmin.expects(AdminProductDetail.variantsModal).not.toBeVisible();
            }
        };

        await use(task);
    },
});
