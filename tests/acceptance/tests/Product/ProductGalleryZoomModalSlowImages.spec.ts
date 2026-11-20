import { test } from '@fixtures/AcceptanceTest';

test(
    'As a shop customer, I want the zoom modal to show loaded images when I click the gallery image repeatedly on a slow connection.',
    {
        tag: [
            '@Product',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, TestDataService, StorefrontProductDetail }) => {
        const product = await TestDataService.createBasicProduct();
        const firstMedia = await TestDataService.createMediaPNG();
        const secondMedia = await TestDataService.createMediaPNG();

        await TestDataService.assignProductMedia(product.id, firstMedia.id);
        await TestDataService.assignProductMedia(product.id, secondMedia.id);

        const page = StorefrontProductDetail.page;

        await page.route(/\/(media|thumbnail)\//, async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 1500));
            await route.continue();
        });

        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));

        const galleryImage = page.locator('.gallery-slider-item .js-magnifier-image').first();
        const zoomModal = page.locator('.js-zoom-modal');

        await galleryImage.dblclick();
        await galleryImage.click();

        await ShopCustomer.expects(zoomModal).toBeVisible();

        const modalImages = zoomModal.locator('.js-load-img');

        const sizes = await modalImages.evaluateAll((images) =>
            images.map((image) => (image as HTMLImageElement).naturalWidth),
        );

        test.expect(sizes.length).toBeGreaterThan(0);
        test.expect(sizes.every((size) => size > 0)).toBe(true);

        await ShopCustomer.expects(zoomModal.locator('.tns-slide-active .gallery-slider-image').first()).toBeVisible();
    },
);
