import { test as base } from 'playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import { expect } from '@fixtures/AcceptanceTest';
import { components } from '@shopware/api-client/admin-api-types';
import { createRandomImage } from '@fixtures/Helper';
import fs from 'fs';

export const MediaData = base.extend<FixtureTypes>({
    mediaData: async ({ adminApiContext, idProvider }, use) => {

        const imageId = idProvider.getIdPair().id;
        const imageFilePath = `./tmp/image-${imageId}.png`;

        // Create random image
        const image = createRandomImage();

        if (!fs.existsSync('./tmp/')) {
            try {
                fs.mkdirSync('./tmp/');
            } catch (err) {
                console.error(err);
            }
        }

        fs.writeFileSync(imageFilePath, image.toBuffer());


        // Create empty media and use the mediaId for Upload
        const mediaResponse = await adminApiContext.post('./media?_response', {
            data: {
                private: false,
            },
        });

        await expect(mediaResponse.ok()).toBeTruthy();

        // Allow access to new media in test
        const { data: media } = (await mediaResponse.json()) as { data: components['schemas']['Media'] };

        // Upload binary png media
        const mediaCreationResponse = await adminApiContext.post(`./_action/media/${media.id}/upload?extension=png&fileName=${media.id}`, {
            data: fs.readFileSync(imageFilePath),
            headers: {
                'content-type': 'image/png',
            },
        });

        await expect(mediaCreationResponse.ok()).toBeTruthy();

        // Define Tags and make tag definitions available in media object
        const altTag = media.alt = `alt-${media.id}`;
        const titleTag = media.title = `title-${media.id}`;

        // Provide alt and title tag to media
        const editMediaResponse = await adminApiContext.patch(`./media/${media.id}`, {
            data: {
                alt: altTag,
                title: titleTag,
            },
        });
        await expect(editMediaResponse.ok()).toBeTruthy();

        // Use media data in the test
        await use(media);

        // Delete image from dir
        fs.unlink(imageFilePath, (err) => {
            if (err) {
                throw err;
            }
        });

        // Delete media after the test is done
        const cleanupResponse = await adminApiContext.delete(`./media/${media.id}`);
        await expect(cleanupResponse.ok()).toBeTruthy();
    },
});
