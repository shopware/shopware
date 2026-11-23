import { test as base } from '@playwright/test';
import type { FixtureTypes, Task, ProductDocumentType } from '@fixtures/AcceptanceTest';
import { createDocument, getMediaId } from '@fixtures/AcceptanceTest';

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
    UploadDocument: async ({ AdminProductDetail, AdminApiContext }, use) => {
        const uploadedMediaNames: string[] = [];

        const task = (documentName: string, options: UploadDocumentOptions = {}) => {
            return async function UploadDocument() {
                uploadedMediaNames.push(documentName);

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
                    AdminProductDetail.addDocumentsModal.waitFor({ state: 'hidden' }).catch(() => {}),
                    AdminProductDetail.documentUploadError.waitFor({ state: 'visible' }).catch(() => {}),
                ]);
            };
        };

        await use(task);

        // Best-effort cleanup: delete the uploaded media (a rejected upload created none).
        for (const mediaName of uploadedMediaNames) {
            try {
                const mediaId = await getMediaId(mediaName, AdminApiContext);
                if (mediaId) {
                    await AdminApiContext.delete(`media/${mediaId}`);
                }
            } catch {
                // Nothing to clean up.
            }
        }
    },
});
