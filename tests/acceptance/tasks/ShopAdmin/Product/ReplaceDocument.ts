import { test as base } from '@playwright/test';
import type { FixtureTypes, Task, ProductDocumentType } from '@fixtures/AcceptanceTest';
import { createDocument } from '@fixtures/AcceptanceTest';
import { trackUploadedDocumentMedia } from '@tasks/trackDocumentMedia';

export interface ReplaceDocumentOptions {
    type?: ProductDocumentType;
    sizeInBytes?: number;
}

/**
 * Replaces the file of a product document via the Edit document modal: open the row menu, choose
 * Edit, remove the current file and upload a fresh one. The title and position stay the same.
 */
export const ReplaceDocument = base.extend<{ ReplaceDocument: Task }, FixtureTypes>({
    ReplaceDocument: async ({ AdminProductDetail, AdminApiContext, TestDataService }, use) => {
        const task = (documentName: string, options: ReplaceDocumentOptions = {}) => {
            return async function ReplaceDocument() {
                const document = createDocument(options.type, { sizeInBytes: options.sizeInBytes });

                await AdminProductDetail.documentRowMenuButton(documentName).click();
                await AdminProductDetail.documentRowMenuEdit.click();
                await AdminProductDetail.editDocumentRemoveFileButton.click();

                const fileChooserPromise = AdminProductDetail.page.waitForEvent('filechooser');
                await AdminProductDetail.editDocumentUploadFileButton.click();
                const fileChooser = await fileChooserPromise;
                await fileChooser.setFiles({
                    name: `${documentName}.${document.extension}`,
                    mimeType: document.mimeType,
                    buffer: document.buffer,
                });

                // Wait for the new file to finish uploading before applying, or Apply is a no-op.
                await AdminProductDetail.editDocumentRemoveFileButton.waitFor({ state: 'visible' });
                await AdminProductDetail.editDocumentApplyButton.click();
                await AdminProductDetail.editDocumentModal.waitFor({ state: 'hidden' });

                // The replacement file is a new, independent media; register it so cleanup removes it.
                await trackUploadedDocumentMedia(TestDataService, AdminApiContext, documentName);
            };
        };

        await use(task);
    },
});
