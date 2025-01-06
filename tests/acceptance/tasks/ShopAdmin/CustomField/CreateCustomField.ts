import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const CreateCustomField = base.extend<{ CreateCustomField: Task }, FixtureTypes>({
    CreateCustomField: async ({ AdminCustomFieldDetail }, use) => {
        const task = (customFieldName: string, customFieldType: 'text' | 'number') => {
            return async function CreateCustomField() {
                await AdminCustomFieldDetail.newCustomFieldButton.click();
                await AdminCustomFieldDetail.customFieldTypeSelectionList.selectOption(customFieldType);
                await AdminCustomFieldDetail.customFieldTechnicalNameInput.fill(customFieldName);
                await AdminCustomFieldDetail.customFieldLabelEnglishGBInput.fill(customFieldName);
                await AdminCustomFieldDetail.customFieldAddButton.click();
            }
        };
        await use(task);
    },
});
