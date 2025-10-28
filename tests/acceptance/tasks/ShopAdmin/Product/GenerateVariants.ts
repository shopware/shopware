import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const GenerateVariants = base.extend<{ GenerateVariants: Task }, FixtureTypes>({
    GenerateVariants: async ({ ShopAdmin, AdminProductDetail }, use) => {
        const task = (colorProperty: string, sizeProperty: string) => {
            return async function GenerateVariants() {
                // Navigate to variants tab
                await AdminProductDetail.variantsTabLink.click();
                await AdminProductDetail.generateVariantsButton.click();
                await ShopAdmin.expects(AdminProductDetail.variantsModalHeadline).toBeVisible();

                // Select color properties
                await AdminProductDetail.propertyName(colorProperty).click();

                await AdminProductDetail.propertyValueCheckbox('Blue').check();
                await ShopAdmin.expects(AdminProductDetail.propertyValueCheckbox('Blue')).toBeChecked();

                await AdminProductDetail.propertyValueCheckbox('Red').check();
                await ShopAdmin.expects(AdminProductDetail.propertyValueCheckbox('Red')).toBeChecked();

                // Select size properties
                await AdminProductDetail.propertyName(sizeProperty).click();

                await AdminProductDetail.propertyValueCheckbox('Medium').check();
                await ShopAdmin.expects(AdminProductDetail.propertyValueCheckbox('Medium')).toBeChecked();

                await AdminProductDetail.propertyValueCheckbox('Large').check();
                await ShopAdmin.expects(AdminProductDetail.propertyValueCheckbox('Large')).toBeChecked();

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
