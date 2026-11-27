import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

/**
 * Removes a product document via the row context menu (··· -> Remove).
 * The media file stays in the media library.
 */
export const DeleteDocument = base.extend<{ DeleteDocument: Task }, FixtureTypes>({
    DeleteDocument: async ({ AdminProductDetail }, use) => {
        const task = (documentName: string) => {
            return async function DeleteDocument() {
                await AdminProductDetail.documentRowMenuButton(documentName).click();
                await AdminProductDetail.documentRowMenuRemove.click();
                // Confirm the removal dialog and wait for it to close before the following save.
                await AdminProductDetail.removeDocumentConfirmButton.click();
                await AdminProductDetail.removeDocumentConfirmButton.waitFor({ state: 'hidden' });
            };
        };

        await use(task);
    },
});
