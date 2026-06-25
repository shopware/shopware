import { test, expect } from '@fixtures/AcceptanceTest';

test('Visual: Storefront CMS sliders with vertical alignment', { 
    tag: '@Visual',
    annotation: {
        type: 'issue',
        description: 'https://github.com/shopware/shopware/issues/15253',
  }, 
}, async ({
    ShopCustomer,
    TestDataService,
    IdProvider,
    StorefrontHome,
}) => {
    await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

    const firstMedia = await TestDataService.createMediaPNGSolid(1200, 800, [255, 0, 0]);
    const secondMedia = await TestDataService.createMediaPNGSolid(1000, 700, [0, 150, 255]);

    const imageSliderBlockId = IdProvider.getIdPair().uuid;
    const imageGalleryBlockId = IdProvider.getIdPair().uuid;

    const layout = await TestDataService.createBasicPageLayout('page', {
        name: 'Visual Slider Layout',
        sections: [
            {
                type: 'default',
                sizingMode: 'full_width',
                position: 0,
                blocks: [
                    {
                        id: imageSliderBlockId,
                        type: 'image-slider',
                        position: 0,
                        sectionPosition: 'main',
                        slots: [
                            {
                                slot: 'imageSlider',
                                type: 'image-slider',
                                blockId: imageSliderBlockId,
                                config: {
                                    sliderItems: {
                                        source: 'static',
                                        value: [{ mediaId: firstMedia.id }, { mediaId: secondMedia.id }],
                                    },
                                    displayMode: {
                                        source: 'static',
                                        value: 'contain',
                                    },
                                    minHeight: {
                                        source: 'static',
                                        value: '360px',
                                    },
                                    verticalAlign: {
                                        source: 'static',
                                        value: 'center',
                                    },
                                    navigationArrows: {
                                        source: 'static',
                                        value: 'outside',
                                    },
                                    navigationDots: {
                                        source: 'static',
                                        value: 'none',
                                    },
                                    autoSlide: {
                                        source: 'static',
                                        value: false,
                                    },
                                    speed: {
                                        source: 'static',
                                        value: 300,
                                    },
                                    autoplayTimeout: {
                                        source: 'static',
                                        value: 5000,
                                    },
                                },
                            },
                        ],
                    },
                    {
                        id: imageGalleryBlockId,
                        type: 'image-gallery',
                        position: 1,
                        sectionPosition: 'main',
                        slots: [
                            {
                                slot: 'imageGallery',
                                type: 'image-gallery',
                                blockId: imageGalleryBlockId,
                                config: {
                                    sliderItems: {
                                        source: 'static',
                                        value: [{ mediaId: firstMedia.id }, { mediaId: secondMedia.id }],
                                    },
                                    displayMode: {
                                        source: 'static',
                                        value: 'standard',
                                    },
                                    minHeight: {
                                        source: 'static',
                                        value: '360px',
                                    },
                                    verticalAlign: {
                                        source: 'static',
                                        value: 'flex-end',
                                    },
                                    navigationArrows: {
                                        source: 'static',
                                        value: 'inside',
                                    },
                                    navigationDots: {
                                        source: 'static',
                                        value: 'none',
                                    },
                                    galleryPosition: {
                                        source: 'static',
                                        value: 'left',
                                    },
                                    zoom: {
                                        source: 'static',
                                        value: false,
                                    },
                                    fullScreen: {
                                        source: 'static',
                                        value: false,
                                    },
                                },
                            },
                        ],
                    },
                ],
            },
        ],
    });

    const category = await TestDataService.createCategory({
        name: 'Visual Slider Category',
        cmsPageId: layout.id,
    });

    await test.step('Render sliders and take screenshot.', async () => {
        await ShopCustomer.goesTo(`/navigation/${category.id}`);

        const imageSliderContainer = StorefrontHome.page.locator('.image-slider-container.has-vertical-align.is-align-center');
        const gallerySliderContainer = StorefrontHome.page.locator('.gallery-slider-container.has-vertical-align.is-align-bottom');

        await expect(imageSliderContainer).toBeVisible();
        await expect(gallerySliderContainer).toBeVisible();

        await expect(StorefrontHome.page.locator('main .cms-section')).toHaveScreenshot('Cms-Slider-Vertical-Align.png');
    });
});
