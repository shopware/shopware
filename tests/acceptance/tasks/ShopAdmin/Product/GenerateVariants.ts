import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const GenerateVariants = base.extend<{ GenerateVariants: Task }, FixtureTypes>({
    GenerateVariants: async ({ ShopAdmin, AdminProductDetail }, use) => {
        const task = (colorGroupName: string, sizeGroupName: string ) => {
            return async function GenerateVariants() {
                // Navigate to variants tab
                await AdminProductDetail.variantsTabLink.click();
                await AdminProductDetail.generateVariantsButton.click();
                await ShopAdmin.expects(AdminProductDetail.variantsModalHeadline).toBeVisible();
                await ShopAdmin.expects(AdminProductDetail.propertyGroup(colorGroupName)).toBeVisible();

                // Select color properties
                await AdminProductDetail.propertyGroup(colorGroupName).click();

                await AdminProductDetail.propertyGroupValueCheckbox('Blue').check();
                await ShopAdmin.expects(AdminProductDetail.propertyGroupValueCheckbox('Blue')).toBeChecked();

                await AdminProductDetail.propertyGroupValueCheckbox('Red').check();
                await ShopAdmin.expects(AdminProductDetail.propertyGroupValueCheckbox('Red')).toBeChecked();

                // Select size properties
                await AdminProductDetail.propertyGroup(sizeGroupName).click();

                await AdminProductDetail.propertyGroupValueCheckbox('Medium').check();
                await ShopAdmin.expects(AdminProductDetail.propertyGroupValueCheckbox('Medium')).toBeChecked();

                await AdminProductDetail.propertyGroupValueCheckbox('Large').check();
                await ShopAdmin.expects(AdminProductDetail.propertyGroupValueCheckbox('Large')).toBeChecked();

                // Proceed to generate variants
                await AdminProductDetail.variantsNextButton.click();
                await ShopAdmin.expects(AdminProductDetail.page.getByText('4 variants will be added, 0 variants will be deleted.')).toBeVisible();

                // Save variants
                await AdminProductDetail.variantsSaveButton.click();
                await ShopAdmin.expects(AdminProductDetail.variantsModal).not.toBeVisible({ timeout: 30000 });
            }
        };

        await use(task);
    },
});
