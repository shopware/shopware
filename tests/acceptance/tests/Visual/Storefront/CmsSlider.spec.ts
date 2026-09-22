import { test, expect } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test(
    'Visual: Storefront CMS sliders with vertical alignment',
    {
        tag: '@Visual',
        annotation: {
            type: 'issue',
            description: 'https://github.com/shopware/shopware/issues/15253',
        },
    },
    async ({ ShopCustomer, TestDataService, IdProvider, StorefrontHome }) => {
        await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

        const firstMedia = await TestDataService.createMediaPNGSolid(
            1200,
            800,
            [
                255,
                0,
                0,
            ],
        );
        const secondMedia = await TestDataService.createMediaPNGSolid(
            1000,
            700,
            [
                0,
                150,
                255,
            ],
        );

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
                                            value: [
                                                { mediaId: firstMedia.id },
                                                { mediaId: secondMedia.id },
                                            ],
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
                                            value: [
                                                { mediaId: firstMedia.id },
                                                { mediaId: secondMedia.id },
                                            ],
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

            const imageSliderContainer = StorefrontHome.page.locator(
                '.image-slider-container.has-vertical-align.is-align-center',
            );
            const gallerySliderContainer = StorefrontHome.page.locator(
                '.gallery-slider-container.has-vertical-align.is-align-bottom',
            );

            await expect(imageSliderContainer).toBeVisible();
            await expect(gallerySliderContainer).toBeVisible();

            await expect(StorefrontHome.page.locator('main .cms-section')).toHaveScreenshot('Cms-Slider-Vertical-Align.png');
        });
    },
);

test(
    'Visual: Storefront standard image gallery images are centered',
    {
        tag: '@Visual',
        annotation: {
            type: 'issue',
            description: 'https://github.com/shopware/shopware/issues/20286',
        },
    },
    async ({ ShopCustomer, TestDataService, IdProvider, StorefrontHome, InstanceMeta }) => {
        test.skip(
            satisfies(InstanceMeta.version, '<6.7.15.0'),
            'Standard image gallery centering is fixed in version 6.7.15.0',
        );

        await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

        const firstMedia = await TestDataService.createMediaPNGSolid(
            800,
            600,
            [
                255,
                0,
                0,
            ],
        );
        const secondMedia = await TestDataService.createMediaPNGSolid(
            700,
            500,
            [
                0,
                150,
                255,
            ],
        );
        const imageGalleryBlockId = IdProvider.getIdPair().uuid;

        const layout = await TestDataService.createBasicPageLayout('page', {
            name: 'Standard Image Gallery Layout',
            sections: [
                {
                    type: 'default',
                    sizingMode: 'boxed',
                    position: 0,
                    blocks: [
                        {
                            id: imageGalleryBlockId,
                            type: 'image-gallery',
                            position: 0,
                            sectionPosition: 'main',
                            slots: [
                                {
                                    slot: 'imageGallery',
                                    type: 'image-gallery',
                                    blockId: imageGalleryBlockId,
                                    config: {
                                        sliderItems: {
                                            source: 'static',
                                            value: [
                                                { mediaId: firstMedia.id },
                                                { mediaId: secondMedia.id },
                                            ],
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
            name: 'Standard Image Gallery Category',
            cmsPageId: layout.id,
        });

        await ShopCustomer.goesTo(`/navigation/${category.id}`);
        await ShopCustomer.presses(StorefrontHome.consentAcceptAllCookiesButton);
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();

        const gallerySliderContainer = StorefrontHome.page.locator(
            '.gallery-slider-container.has-vertical-align.is-align-bottom',
        );
        const gallerySliderItem = gallerySliderContainer.locator('.gallery-slider-item.is-standard').first();
        const gallerySliderImage = gallerySliderItem.locator('.gallery-slider-image');

        await expect(gallerySliderContainer).toBeVisible();
        await expect(gallerySliderImage).toBeVisible();

        const [
            galleryItemBox,
            galleryImageBox,
        ] = await Promise.all([
            gallerySliderItem.boundingBox(),
            gallerySliderImage.boundingBox(),
        ]);

        expect(galleryItemBox).not.toBeNull();
        expect(galleryImageBox).not.toBeNull();
        expect(galleryImageBox!.x - galleryItemBox!.x).toBeCloseTo((galleryItemBox!.width - galleryImageBox!.width) / 2, 0);

        await expect(StorefrontHome.page.locator('main .cms-section')).toHaveScreenshot(
            'Cms-Slider-Standard-Image-Gallery-Centered.png',
        );
    },
);
