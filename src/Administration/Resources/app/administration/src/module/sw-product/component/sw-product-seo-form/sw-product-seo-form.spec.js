/**
 * @sw-package inventory
 */

import { createWrapper } from './sw-product-seo-form.spec/create-wrapper';

describe('module/sw-product/component/sw-product-seo-form', () => {
    /** @tupe Wrapper */
    let wrapper;

    it('should not be visible if there are not variants', async () => {
        const productEntity = {
            canonicalProductId: null,
            childCount: 0,
            metaTitle: 'title',
        };

        wrapper = await createWrapper(productEntity);
        await flushPromises();

        const switchComponent = wrapper.find('.sw-field--switch');
        const singleSelectComponent = wrapper.find('.sw-entity-single-select');

        expect(switchComponent.exists()).toBe(false);
        expect(singleSelectComponent.exists()).toBe(false);
    });

    it('should not be visible if there is a parent product', async () => {
        const productEntity = {
            canonicalProductId: null,
            childCount: 2,
            metaTitle: 'title',
        };

        const parentProduct = {
            id: 'parent-id',
        };

        wrapper = await createWrapper(productEntity, parentProduct);
        await flushPromises();

        const switchComponent = wrapper.find('.sw-field--switch');
        const singleSelectComponent = wrapper.find('.sw-entity-single-select');

        expect(switchComponent.exists()).toBe(false);
        expect(singleSelectComponent.exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should have a disabled select and a turned off switch if there is no canonical url',
        async () => {
            const productEntity = {
                canonicalProductId: null,
                childCount: 3,
                metaTitle: 'title',
            };

            wrapper = await createWrapper(productEntity);
            await flushPromises();

            const switchComponent = wrapper.getComponent('.mt-switch');
            const singleSelectComponent = wrapper.find('.sw-entity-single-select');

            // check if switch is off
            expect(switchComponent.vm.checked).toBe(false);

            // check if single select is disabled
            expect(singleSelectComponent.classes('is--disabled')).toBe(true);
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should have a selected value if there is a canonical url in the Vuex store',
        async () => {
            const productEntity = {
                id: 'product-id',
                canonicalProductId: 'first',
                childCount: 3,
                metaTitle: 'title',
            };

            wrapper = await createWrapper(productEntity);
            await flushPromises();

            const switchComponent = wrapper.getComponent('.mt-switch');
            const singleSelectComponent = wrapper.get('.sw-entity-single-select');

            // check if switch is turned on
            expect(switchComponent.vm.modelValue).toBe(true);

            // check if single select is enabled
            expect(singleSelectComponent.attributes('disabled')).toBeUndefined();

            // check value of select field
            const textOfSelectField = singleSelectComponent.find('.sw-product-variant-info__product-name').text();
            expect(textOfSelectField).toBe('first');
        },
    );

    it('should show the inherited OG image for variants', async () => {
        const parentMedia = {
            id: 'parent-media-id',
            url: 'https://example.com/parent-image.jpg',
        };

        const productEntity = {
            id: 'child-id',
            childCount: 0,
            metaTitle: 'title',
            openGraphMediaId: null,
            openGraphMedia: null,
        };

        const parentProduct = {
            id: 'parent-id',
            openGraphMediaId: parentMedia.id,
            openGraphMedia: parentMedia,
        };

        wrapper = await createWrapper(productEntity, parentProduct);
        await flushPromises();

        const mediaUpload = wrapper.getComponent({ name: 'sw-media-upload-v2' });

        expect(mediaUpload.props('source')).toEqual(parentMedia);
    });

    it('should show the length of the meta title and description against the recommended length', async () => {
        wrapper = await createWrapper({ metaTitle: 'Title', metaDescription: 'a'.repeat(156), childCount: 0 });
        await flushPromises();

        const hints = wrapper.findAll('.sw-product-seo-form__recommended-length');

        expect(hints).toHaveLength(2);
        expect(hints[0].text()).toBe('sw-product.seoForm.recommendedLength');
        expect(hints[0].classes()).not.toContain('is--exceeded');
        expect(hints[1].classes()).toContain('is--exceeded');
    });

    it('should count empty and inherited values for the recommended length', async () => {
        wrapper = await createWrapper(
            { metaTitle: null, metaDescription: null, childCount: 0 },
            { id: 'parent-id', metaTitle: 'a'.repeat(57), metaDescription: null },
        );
        await flushPromises();

        const hints = wrapper.findAll('.sw-product-seo-form__recommended-length');

        expect(hints[0].classes()).toContain('is--exceeded');
        expect(hints[1].classes()).not.toContain('is--exceeded');
        expect(wrapper.vm.getRecommendedLengthClass(undefined, 56)).toEqual({ 'is--exceeded': false });
    });
});
