/* eslint-disable max-len */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-extensions-store-slider';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-extension-store-slider'), {
        stubs: {
            'sw-icon': {
                template: '<div class="sw-icon"></div>'
            }
        },
        provide: {},
        mocks: {
            $tc: t => t
        },
        propsData: {
            images: []
        }
    });
}

describe('src/module/sw-extension-store/component/sw-extension-store-slider', () => {
    /** @type Wrapper */
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show a placeholder image if no images are provided', async () => {
        // TODO: a fallback is not implemented now
    });

    it('should show a single image', async () => {
        const images = ['https://via.placeholder.com/800x400'];
        await wrapper.setProps({
            images: images
        });

        const singleImage = wrapper.findAll('img');
        expect(singleImage.length).toBe(1);
        expect(singleImage.at(0).attributes()).toHaveProperty('src');
        expect(singleImage.at(0).attributes().src).toEqual(images[0]);
    });

    [
        { slideCount: 2, imagesLength: 4 },
        { slideCount: 1, imagesLength: 2 },
        { slideCount: 3, imagesLength: 6 }
    ].forEach((sample) => {
        it(`should show the navigation only when images.length (${sample.imagesLength}) is higher than slideCount (${sample.slideCount})`, async () => {
            await wrapper.setProps({
                images: Array(sample.imagesLength).map(() => 'https://via.placeholder.com/800x400'),
                slideCount: sample.slideCount
            });

            const sliderNavigation = wrapper.find('.sw-extension-store-slider__navigation');
            expect(sliderNavigation.exists()).toBe(true);
            expect(sliderNavigation.isVisible()).toBe(true);
        });
    });

    [
        { slideCount: 1, imagesLength: 1 },
        { slideCount: 2, imagesLength: 1 },
        { slideCount: 2, imagesLength: 2 },
        { slideCount: 1, imagesLength: 1 },
        { slideCount: 3, imagesLength: 3 }
    ].forEach((sample) => {
        it(`should not show the navigation only when images.length (${sample.imagesLength}) is lower or equal than slideCount (${sample.slideCount})`, async () => {
            await wrapper.setProps({
                images: Array(sample.imagesLength).map(() => 'https://via.placeholder.com/800x400'),
                slideCount: sample.slideCount
            });

            const sliderNavigation = wrapper.find('.sw-extension-store-slider__navigation');
            expect(sliderNavigation.exists()).toBe(false);
        });
    });

    [1, 2, 3].forEach((slideCount) => {
        it(`should show the number of active images of slide count (${slideCount})`, async () => {
            const images = [
                'https://via.placeholder.com/800x400',
                'https://via.placeholder.com/800x400',
                'https://via.placeholder.com/800x400',
                'https://via.placeholder.com/800x400',
                'https://via.placeholder.com/800x400',
                'https://via.placeholder.com/800x400',
                'https://via.placeholder.com/800x400'
            ];

            await wrapper.setProps({
                images: images,
                slideCount: slideCount
            });

            const activeItems = wrapper.findAll('.sw-extension-store-slider__slide-item.is--active');
            expect(activeItems.length).toBe(slideCount);
        });
    });

    it('should not set a higher slide count than maxSlides(3)', async () => {
        const images = [
            'https://via.placeholder.com/800x400',
            'https://via.placeholder.com/800x400',
            'https://via.placeholder.com/800x400',
            'https://via.placeholder.com/800x400',
            'https://via.placeholder.com/800x400',
            'https://via.placeholder.com/800x400',
            'https://via.placeholder.com/800x400'
        ];

        await wrapper.setProps({
            images: images,
            slideCount: 5
        });

        const activeItems = wrapper.findAll('.sw-extension-store-slider__slide-item.is--active');
        expect(activeItems.length).toBe(3);
    });

    it('should show multiple images at the same time', async () => {
        const images = [
            'https://via.placeholder.com/100x400',
            'https://via.placeholder.com/200x400',
            'https://via.placeholder.com/300x400',
            'https://via.placeholder.com/400x400',
            'https://via.placeholder.com/500x400',
            'https://via.placeholder.com/600x400',
            'https://via.placeholder.com/700x400'
        ];

        await wrapper.setProps({
            images: images,
            slideCount: 3
        });

        const activeItems = wrapper.findAll('.sw-extension-store-slider__slide-item.is--active');
        expect(activeItems.length).toBe(3);

        // check if image sources are set correctly
        activeItems.wrappers.forEach(activeItem => {
            const dataKey = activeItem.attributes()['data-key'];
            expect(activeItem.find('img').attributes().src).toEqual(images[dataKey]);
        });
    });

    it('should navigate forward', async () => {
        const images = [
            'https://via.placeholder.com/100x400',
            'https://via.placeholder.com/200x400',
            'https://via.placeholder.com/300x400',
            'https://via.placeholder.com/400x400',
            'https://via.placeholder.com/500x400',
            'https://via.placeholder.com/600x400',
            'https://via.placeholder.com/700x400'
        ];

        await wrapper.setProps({
            images: images,
            slideCount: 1
        });

        const buttonNext = wrapper.find('.sw-extension-store-slider__btn-next');

        const firstItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="0"]');
        const secondItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="1"]');
        const thirdItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="2"]');

        // first item should be the active item by default
        expect(firstItem.classes()).toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).not.toContain('is--active');

        // go to second item
        await buttonNext.trigger('click');

        // second item should be the active item now
        expect(firstItem.classes()).not.toContain('is--active');
        expect(secondItem.classes()).toContain('is--active');
        expect(thirdItem.classes()).not.toContain('is--active');

        // go to third item
        await buttonNext.trigger('click');

        // third item should be the active item now
        expect(firstItem.classes()).not.toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).toContain('is--active');
    });
    it('should navigate backward', async () => {
        const images = [
            'https://via.placeholder.com/100x400',
            'https://via.placeholder.com/200x400',
            'https://via.placeholder.com/300x400',
            'https://via.placeholder.com/400x400',
            'https://via.placeholder.com/500x400',
            'https://via.placeholder.com/600x400',
            'https://via.placeholder.com/700x400'
        ];

        await wrapper.setProps({
            images: images,
            slideCount: 1
        });

        const buttonNext = wrapper.find('.sw-extension-store-slider__btn-next');
        const buttonBack = wrapper.find('.sw-extension-store-slider__btn-back');

        const firstItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="0"]');
        const secondItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="1"]');
        const thirdItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="2"]');

        // first item should be the active item by default
        expect(firstItem.classes()).toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).not.toContain('is--active');

        // go to third item
        await buttonNext.trigger('click');
        await buttonNext.trigger('click');

        // third item should be the active item now
        expect(firstItem.classes()).not.toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).toContain('is--active');

        // go back to second item
        await buttonBack.trigger('click');

        // second item should be the active item now
        expect(firstItem.classes()).not.toContain('is--active');
        expect(secondItem.classes()).toContain('is--active');
        expect(thirdItem.classes()).not.toContain('is--active');

        // go back to first item
        await buttonBack.trigger('click');

        // second item should be the active item now
        expect(firstItem.classes()).toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).not.toContain('is--active');
    });

    it('should not navigate back when user see first image and infinite is off', async () => {
        const images = [
            'https://via.placeholder.com/100x400',
            'https://via.placeholder.com/200x400',
            'https://via.placeholder.com/300x400'
        ];

        await wrapper.setProps({
            images: images,
            slideCount: 1
        });

        const buttonBack = wrapper.find('.sw-extension-store-slider__btn-back');

        const firstItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="0"]');
        const secondItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="1"]');
        const thirdItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="2"]');

        // first item should be the active item by default
        expect(firstItem.classes()).toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).not.toContain('is--active');

        // back button should be disabled
        expect(buttonBack.attributes().disabled).toBe('disabled');

        // a click on this should not trigger anything
        await buttonBack.trigger('click');

        // first item should stay the active item
        expect(firstItem.classes()).toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).not.toContain('is--active');
    });

    it('should navigate back when user see first image and infinite is on', async () => {
        const images = [
            'https://via.placeholder.com/100x400',
            'https://via.placeholder.com/200x400',
            'https://via.placeholder.com/300x400'
        ];

        await wrapper.setProps({
            images: images,
            slideCount: 1,
            infinite: true
        });

        const buttonBack = wrapper.find('.sw-extension-store-slider__btn-back');

        const firstItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="0"]');
        const secondItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="1"]');
        const thirdItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="2"]');

        // first item should be the active item by default
        expect(firstItem.classes()).toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).not.toContain('is--active');

        // back button should not be disabled
        expect(buttonBack.attributes().disabled).toBe(undefined);

        // go to image before
        await buttonBack.trigger('click');

        // third item should now be the active item
        expect(firstItem.classes()).not.toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).toContain('is--active');
    });

    it('should not navigate forward when user see last image and infinite is off', async () => {
        const images = [
            'https://via.placeholder.com/100x400',
            'https://via.placeholder.com/200x400',
            'https://via.placeholder.com/300x400'
        ];

        await wrapper.setProps({
            images: images,
            slideCount: 1
        });

        const buttonNext = wrapper.find('.sw-extension-store-slider__btn-next');

        const firstItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="0"]');
        const secondItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="1"]');
        const thirdItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="2"]');

        // first item should be the active item by default
        expect(firstItem.classes()).toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).not.toContain('is--active');

        // go to third image
        await buttonNext.trigger('click');
        await buttonNext.trigger('click');

        // third item should be the active item
        expect(firstItem.classes()).not.toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).toContain('is--active');

        // next button should be disabled
        expect(buttonNext.attributes().disabled).toBe('disabled');

        // a click on this should not trigger anything
        await buttonNext.trigger('click');

        // third item should stay the active item
        expect(firstItem.classes()).not.toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).toContain('is--active');
    });

    it('should navigate forward when user see last image and infinite is on', async () => {
        const images = [
            'https://via.placeholder.com/100x400',
            'https://via.placeholder.com/200x400',
            'https://via.placeholder.com/300x400'
        ];

        await wrapper.setProps({
            images: images,
            slideCount: 1,
            infinite: true
        });

        const buttonNext = wrapper.find('.sw-extension-store-slider__btn-next');

        const firstItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="0"]');
        const secondItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="1"]');
        const thirdItem = wrapper.find('.sw-extension-store-slider__slide-item[data-key="2"]');

        // first item should be the active item by default
        expect(firstItem.classes()).toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).not.toContain('is--active');

        // go to third image
        await buttonNext.trigger('click');
        await buttonNext.trigger('click');

        // third item should be the active item
        expect(firstItem.classes()).not.toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).toContain('is--active');

        // next button should not be disabled
        expect(buttonNext.attributes().disabled).toBe(undefined);

        // a click on this should trigger next image
        await buttonNext.trigger('click');

        // first item should now be the active item
        expect(firstItem.classes()).toContain('is--active');
        expect(secondItem.classes()).not.toContain('is--active');
        expect(thirdItem.classes()).not.toContain('is--active');
    });

    it('should show a small slider as default', async () => {
        const images = [
            'https://via.placeholder.com/100x400',
            'https://via.placeholder.com/200x400',
            'https://via.placeholder.com/300x400'
        ];

        await wrapper.setProps({
            images: images
        });

        expect(wrapper.classes()).not.toContain('sw-card--large');
    });

    it('should show a large slider when using property', async () => {
        const images = [
            'https://via.placeholder.com/100x400',
            'https://via.placeholder.com/200x400',
            'https://via.placeholder.com/300x400'
        ];

        await wrapper.setProps({
            images: images,
            large: true
        });

        expect(wrapper.classes()).toContain('sw-card--large');
    });
});
