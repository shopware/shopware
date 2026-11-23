import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

/**
 * Overrides a document's title for the active (non-default) language via the row's
 * "Add <languageEnglishName> version" menu entry. Must be saved afterwards to persist.
 */
export const AddDocumentLanguageVersion = base.extend<{ AddDocumentLanguageVersion: Task }, FixtureTypes>({
    AddDocumentLanguageVersion: async ({ AdminProductDetail }, use) => {
        const task = (documentName: string, newName: string, languageEnglishName: string) => {
            return async function AddDocumentLanguageVersion() {
                await AdminProductDetail.documentRowMenuButton(documentName).click();
                await AdminProductDetail.documentRowMenuAddLanguageVersion(languageEnglishName).click();
                await AdminProductDetail.editDocumentNameInput.fill(newName);
                await AdminProductDetail.editDocumentApplyButton.click();
                await AdminProductDetail.editDocumentModal.waitFor({ state: 'hidden' });
            };
        };

        await use(task);
    },
});
