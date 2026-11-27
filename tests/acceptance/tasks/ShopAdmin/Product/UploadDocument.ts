import { test as base } from '@playwright/test';
import type { FixtureTypes, Task, ProductDocumentType } from '@fixtures/AcceptanceTest';
import { createDocument } from '@fixtures/AcceptanceTest';
import { trackUploadedDocumentMedia } from '@tasks/trackDocumentMedia';

export interface UploadDocumentOptions {
    /** File type to generate. Defaults to a valid PDF. Use 'txt' for the unsupported-type case. */
    type?: ProductDocumentType;
    /** Pads the generated file to at least this many bytes, for the oversized-file case. */
    sizeInBytes?: number;
}

/**
 * Uploads a product document via the "Add documents" modal of the product open in the Administration.
 * Success and error assertions stay in the spec, so the same task serves both.
 */
export const UploadDocument = base.extend<{ UploadDocument: Task }, FixtureTypes>({
    UploadDocument: async ({ AdminProductDetail, AdminApiContext, TestDataService }, use) => {
        const task = (documentName: string, options: UploadDocumentOptions = {}) => {
            return async function UploadDocument() {
                const document = createDocument(options.type, { sizeInBytes: options.sizeInBytes });

                // A rejected upload leaves the modal open; close it so the next upload can click Add.
                if (await AdminProductDetail.addDocumentsModal.isVisible()) {
                    await AdminProductDetail.addDocumentsModalCloseButton.click();
                    await AdminProductDetail.addDocumentsModal.waitFor({ state: 'hidden' });
                }

                await AdminProductDetail.addDocumentsButton.click();
                const fileChooserPromise = AdminProductDetail.page.waitForEvent('filechooser');
                await AdminProductDetail.addDocumentsUploadFileButton.click();
                const fileChooser = await fileChooserPromise;
                await fileChooser.setFiles({
                    name: `${documentName}.${document.extension}`,
                    mimeType: document.mimeType,
                    buffer: document.buffer,
                });

                // Wait for the upload to settle (modal closes on success, error shows on rejection)
                // so a following SaveProduct cannot run mid-upload and persist without the media.
                await Promise.race([
                    AdminProductDetail.addDocumentsModal.waitFor({ state: 'hidden' }).catch(() => undefined),
                    AdminProductDetail.documentUploadError.waitFor({ state: 'visible' }).catch(() => undefined),
                ]);

                // The file lives independently of the product; register it so cleanup removes it.
                await trackUploadedDocumentMedia(TestDataService, AdminApiContext, documentName);
            };
        };

        await use(task);
    },
});
