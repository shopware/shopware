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
                    // Meteor < 5.8 does not link the label to the select input, so its accessible name falls back to the placeholder.
                    const dialog = AdminCustomFieldDetail.newCustomFieldDialog;
                    const customFieldTypeSelectionList = dialog
                        .getByRole('textbox', { name: 'Type', exact: true })
                        .or(dialog.getByRole('textbox', { name: 'Select...' }));

                    await (
                        await AdminCustomFieldDetail.getSelectFieldListitem(
                            customFieldTypeSelectionList,
                            customFieldTypeText,
                        )
                    ).click();
                }
                await AdminCustomFieldDetail.customFieldTechnicalNameInput.fill(customFieldName);
                await AdminCustomFieldDetail.customFieldLabelEnglishGBInput.fill(customFieldName);
                await AdminCustomFieldDetail.customFieldAddButton.click();
            };
        };
        await use(task);
    },
});
