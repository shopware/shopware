import { test, expect } from '@fixtures/AcceptanceTest';

const IMAGE_COUNT = 10;
const VISIBLE_THUMBNAILS = 5;

test('Product gallery should lazy-load non-visible images and constrain thumbnail height.', { tag: ['@Product', '@Storefront'] }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
    DefaultSalesChannel,
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
            visibilities: [{
                salesChannelId: DefaultSalesChannel.salesChannel.id,
                visibility: 30,
            }],
        },
    });
    expect(assignResponse.ok()).toBeTruthy();

    await test.step('Thumbnail images beyond the visible count should have loading="lazy".', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));

        const thumbnailImages = StorefrontProductDetail.page.locator('.gallery-slider-thumbnails-image');

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
        const mainSlideImages = StorefrontProductDetail.page.locator('.gallery-slider-container .gallery-slider-image');

        await expect(mainSlideImages).toHaveCount(IMAGE_COUNT);

        await expect(mainSlideImages.first()).toHaveAttribute('loading', 'eager');

        for (let i = 1; i < IMAGE_COUNT; i++) {
            await expect(mainSlideImages.nth(i)).toHaveAttribute('loading', 'lazy');
        }
    });

    await test.step('Thumbnail container height should not exceed the expected maximum.', async () => {
        const thumbnailContainer = StorefrontProductDetail.page.locator('.gallery-slider-thumbnails-col.is-left .gallery-slider-thumbnails');
        const isVisible = await thumbnailContainer.isVisible();

        if (isVisible) {
            const boundingBox = await thumbnailContainer.boundingBox();
            expect(boundingBox).not.toBeNull();
            expect(boundingBox!.height).toBeLessThanOrEqual(500);
        }
    });
});
