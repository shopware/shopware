import { test as base, expect } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
import { createRandomImage, getMediaId } from '@fixtures/AcceptanceTest';

export const UploadImage = base.extend<{ UploadImage: Task }, FixtureTypes>({
    UploadImage: async ({ AdminProductDetail, AdminApiContext }, use ) => {

        let uploadedMediaName: string;

        const task = (imageName: string) => {
            return async function UploadImage() {

                uploadedMediaName = imageName;

                // Create Image
                const image = createRandomImage();

                const fileChooserPromise = AdminProductDetail.page.waitForEvent('filechooser');
                await AdminProductDetail.uploadMediaButton.click();
                const fileChooser = await fileChooserPromise;
                await fileChooser.setFiles({
                    name: `${ imageName }.png`,
                    mimeType: 'image/png',
                    buffer: Buffer.from(image.toBuffer(), 'utf-8'),
                });

                // Wait until media is saved via API
                const response = await AdminProductDetail.page.waitForResponse(`${ process.env['APP_URL'] }api/search/media`);
                expect(response.ok()).toBeTruthy();

                const mediaResourceResponse = await response.json();

                expect(mediaResourceResponse.data[0]).toEqual(
                  expect.objectContaining({
                      attributes: expect.objectContaining({
                          fileName: imageName,
                          mimeType: 'image/png',
                      }),
                  }),
                );
            }
        }

        await use(task);

        // Delete image from database
        if(uploadedMediaName) {
            const uploadedMediaId = await getMediaId(uploadedMediaName, AdminApiContext);
            await AdminApiContext.delete(`media/${uploadedMediaId}`);
        }
    },
});
