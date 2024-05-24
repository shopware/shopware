import { test as base, expect } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
import { createRandomImage, getMediaId } from '@fixtures/AcceptanceTest';
import fs from 'fs';

export const UploadImage = base.extend<{ UploadImage: Task }, FixtureTypes>({
    UploadImage: async ({ AdminProductDetail, AdminApiContext }, use ) => {

        let imageFilePath: string;
        let fileName: string;

        const task = (imageName: string) => {
            return async function UploadImage() {

                fileName = imageName;
                imageFilePath = `./tmp/${ imageName }.png`;

                if (!fs.existsSync('./tmp/')) {
                    try {
                        fs.mkdirSync('./tmp/');
                    } catch (err) {
                        console.error(err);
                    }
                }

                // Create Image
                const image = createRandomImage();
                fs.writeFileSync(imageFilePath, image.toBuffer());

                const fileChooserPromise = AdminProductDetail.page.waitForEvent('filechooser');
                await AdminProductDetail.uploadMediaButton.click();
                const fileChooser = await fileChooserPromise;
                await fileChooser.setFiles(imageFilePath);

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
        const uploadedMediaId = await getMediaId(fileName, AdminApiContext);
        const deleteUploadedMedia = await AdminApiContext.delete(`media/${ uploadedMediaId }`);
        expect(deleteUploadedMedia.ok()).toBeTruthy();

        // Delete image from dir
        fs.unlink(imageFilePath, (err) => {
            if (err) {
                throw err;
            }
        });
    },
});
