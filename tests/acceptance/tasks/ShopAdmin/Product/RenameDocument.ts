import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

/**
 * Renames a product document via the Edit document modal (row menu -> Edit -> Name -> Apply).
 * The product still has to be saved afterwards for the change to persist.
 */
export const RenameDocument = base.extend<{ RenameDocument: Task }, FixtureTypes>({
    RenameDocument: async ({ AdminProductDetail }, use) => {
        const task = (documentName: string, newName: string) => {
            return async function RenameDocument() {
                await AdminProductDetail.documentRowMenuButton(documentName).click();
                await AdminProductDetail.documentRowMenuEdit.click();
                await AdminProductDetail.editDocumentNameInput.fill(newName);
                await AdminProductDetail.editDocumentApplyButton.click();
                await AdminProductDetail.editDocumentModal.waitFor({ state: 'hidden' });
            };
        };

        await use(task);
    },
});
