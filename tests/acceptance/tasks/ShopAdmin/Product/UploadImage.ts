import { test as base, expect } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
import { createRandomImage, encodeImage } from '@fixtures/AcceptanceTest';

const MEDIA_UPLOAD_URL_PATTERN = /\/api\/_action\/media\/([0-9a-f]{32})\/(?:finalize-)?upload/;

export const UploadImage = base.extend<{ UploadImage: Task }, FixtureTypes>({
    UploadImage: async ({ AdminProductDetail, AdminApiContext }, use) => {
        let uploadedMediaId: string | undefined;

        const task = (imageName: string) => {
            return async function UploadImage() {
                // Create Image
                const image = createRandomImage();

                const fileChooserPromise = AdminProductDetail.page.waitForEvent('filechooser');
                await AdminProductDetail.uploadMediaButton.click();
                const fileChooser = await fileChooserPromise;

                // Wait for the upload itself, other media searches can answer before the file is stored.
                // Cloud storage (SaaS) uploads to a presigned URL and then calls finalize-upload instead of upload.
                const uploadResponsePromise = AdminProductDetail.page.waitForResponse((response) =>
                    MEDIA_UPLOAD_URL_PATTERN.test(response.url()),
                );
                await fileChooser.setFiles({
                    name: `${imageName}.png`,
                    mimeType: 'image/png',
                    buffer: encodeImage(image),
                });

                const uploadResponse = await uploadResponsePromise;
                expect(uploadResponse.ok()).toBeTruthy();

                uploadedMediaId = MEDIA_UPLOAD_URL_PATTERN.exec(uploadResponse.url())![1];

                const mediaResponse = await AdminApiContext.get(`media/${uploadedMediaId}`);
                expect(mediaResponse.ok()).toBeTruthy();

                const { data: media } = await mediaResponse.json();
                expect(media).toEqual(
                    expect.objectContaining({
                        fileName: imageName,
                        mimeType: 'image/png',
                    }),
                );
            };
        };

        await use(task);

        // Delete image from database
        if (uploadedMediaId) {
            await AdminApiContext.delete(`media/${uploadedMediaId}`);
        }
    },
});
