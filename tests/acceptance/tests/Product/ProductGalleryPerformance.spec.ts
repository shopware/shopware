import { test, expect } from '@fixtures/AcceptanceTest';

const IMAGE_COUNT = 10;
const VISIBLE_THUMBNAILS = 5;

const SELECTOR_MAIN_SLIDE_IMAGE = '.gallery-slider-container .gallery-slider-item-container.tns-item:not(.tns-slide-cloned) .gallery-slider-image';
const SELECTOR_THUMBNAIL_IMAGE = '.gallery-slider-thumbnails.tns-slider .gallery-slider-thumbnails-image';
const SELECTOR_THUMBNAIL_COLUMN = '.gallery-slider-thumbnails-col.is-left';
const SELECTOR_GALLERY_COLUMN = '.gallery-slider-col';

test('Product gallery should lazy-load non-visible images and constrain thumbnail height.', { tag: ['@Product', '@Storefront'] }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
}) => {
    const product = await TestDataService.createBasicProduct();

    const mediaItems = [];
    for (let i = 0; i < IMAGE_COUNT; i++) {
        mediaItems.push(await TestDataService.createMediaPNG(200, 200));
    }

    const productMediaPayload = mediaItems.map((media) => {
        const productMediaId = TestDataService.IdProvider.getIdPair().uuid;
        return { id: productMediaId, media: { id: media.id } };
    });

    const assignResponse = await TestDataService.AdminApiClient.patch(`product/${product.id}?_response=basic`, {
        data: {
            coverId: productMediaPayload[0].id,
            media: productMediaPayload,
        },
    });
    expect(assignResponse.ok()).toBeTruthy();

    await test.step('Thumbnail images beyond the visible count should have loading="lazy".', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));

        const thumbnailImages = StorefrontProductDetail.page.locator(SELECTOR_THUMBNAIL_IMAGE);

        await expect(thumbnailImages).toHaveCount(IMAGE_COUNT);

        for (let i = 0; i < IMAGE_COUNT; i++) {
            const img = thumbnailImages.nth(i);

            if (i < VISIBLE_THUMBNAILS) {
                await expect(img).toHaveAttribute('loading', 'eager');
            } else {
                await expect(img).toHaveAttribute('loading', 'lazy');
            }
        }
    });

    await test.step('Main slide images beyond the first should have loading="lazy".', async () => {
        const mainSlideImages = StorefrontProductDetail.page.locator(SELECTOR_MAIN_SLIDE_IMAGE);

        await expect(mainSlideImages).toHaveCount(IMAGE_COUNT);

        await expect(mainSlideImages.first()).toHaveAttribute('loading', 'eager');

        for (let i = 1; i < IMAGE_COUNT; i++) {
            await expect(mainSlideImages.nth(i)).toHaveAttribute('loading', 'lazy');
        }
    });

    await test.step('Thumbnail column should not be taller than the gallery column.', async () => {
        const thumbnailColumn = StorefrontProductDetail.page.locator(SELECTOR_THUMBNAIL_COLUMN);
        const galleryColumn = StorefrontProductDetail.page.locator(SELECTOR_GALLERY_COLUMN);

        const thumbBox = await thumbnailColumn.boundingBox();
        const galleryBox = await galleryColumn.boundingBox();

        expect(thumbBox).not.toBeNull();
        expect(galleryBox).not.toBeNull();
        expect(thumbBox!.height).toBeLessThanOrEqual(galleryBox!.height);
    });
});
