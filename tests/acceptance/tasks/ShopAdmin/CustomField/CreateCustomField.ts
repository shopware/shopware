import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

export const CreateCustomField = base.extend<{ CreateCustomField: Task }, FixtureTypes>({
    CreateCustomField: async ({ AdminCustomFieldDetail, InstanceMeta }, use) => {
        const task = (customFieldName: string, customFieldTypeText: 'Text field' | 'Number field') => {
            return async function CreateCustomField() {
                await AdminCustomFieldDetail.newCustomFieldButton.click();
                if (satisfies(InstanceMeta.version, '<6.7')) {
                    await AdminCustomFieldDetail.customFieldTypeSelectionList.selectOption(customFieldTypeText);
                } else {
                    await (await AdminCustomFieldDetail.getSelectFieldListitem(AdminCustomFieldDetail.customFieldTypeSelectionList, customFieldTypeText)).click();
                }
                await AdminCustomFieldDetail.customFieldTechnicalNameInput.fill(customFieldName);
                await AdminCustomFieldDetail.customFieldLabelEnglishGBInput.fill(customFieldName);
                await AdminCustomFieldDetail.customFieldAddButton.click();
            };
        };
        await use(task);
    },
});
