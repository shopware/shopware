/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

const mediaItems = [
    {
        id: '0',
        media: { url: '/administration/static/img/dashboard-logo.svg' },
    },
    {
        id: '1',
        media: { url: 'https://via.placeholder.com/218x229?text=Placeholder1' },
    },
    {
        id: '2',
        media: { url: 'https://via.placeholder.com/218x229?text=Placeholder2' },
    },
    {
        id: '3',
        media: { url: '/administration/static/img/plugin-manager--login.png' },
    },
    {
        id: '4',
        media: { url: '/administration/static/img/sw-login-background.png' },
    },
];

const zoomableImage = {
    naturalWidth: 500,
    naturalHeight: 300,
    offsetWidth: 400,
    offsetHeight: 200,
};

const unzoomableImage = {
    naturalWidth: 400,
    naturalHeight: 200,
    offsetWidth: 400,
    offsetHeight: 200,
};

function getTranslateAmount(itemLength = 1, itemPerPage = 1, expectedIndex = 0) {
    const remainder = itemLength % itemPerPage;
    const totalPage = Math.ceil(itemLength / itemPerPage);

    if (itemPerPage === 1 || remainder === 0 || itemLength <= itemPerPage) {
        return (expectedIndex / totalPage) * 100;
    }

    const itemWidth = 100 / itemLength;
    return expectedIndex === totalPage - 1
        ? ((expectedIndex - 1) * itemPerPage + remainder) * itemWidth
        : expectedIndex * itemPerPage * itemWidth;
}

function createImage(element = null, dimension = {}) {
    let image = element;

    if (!image) {
        image = new Image();
    }

    const { naturalWidth, naturalHeight, offsetWidth, offsetHeight } = dimension;

    Object.defineProperties(image, {
        naturalWidth: {
            value: naturalWidth || 0,
        },
        naturalHeight: {
            value: naturalHeight || 0,
        },
        offsetWidth: {
            value: offsetWidth || 0,
        },
        offsetHeight: {
            value: offsetHeight || 0,
        },
    });

    return image;
}

async function createWrapper(props = {}, listeners = {}) {
    return mount(
        await wrapTestComponent('sw-image-preview-modal', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-icon': true,
                    'sw-image-slider': await wrapTestComponent('sw-image-slider', {
                        sync: true,
                    }),
                },
                listeners,
            },
            props: {
                mediaItems,
                activeItemId: '0',
                ...props,
            },
        },
    );
}

describe('src/app/component/modal/sw-image-preview-modal', () => {
    it('should render the component correctly', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should navigate image slider correctly when clicking on thumbnail item', async () => {
        const wrapper = await createWrapper();
        const thumbnailItems = wrapper.findAll(
            '.sw-image-preview-modal__thumbnail-slider .sw-image-slider__element-container',
        );
        const imageItems = wrapper.findAll('.sw-image-preview-modal__image-slider .sw-image-slider__element-wrapper');

        const containerScrollable = wrapper.find('.sw-image-preview-modal__image-slider .sw-image-slider__image-scrollable');
        const staticStyles = 'width: 500%; gap: 20px;';

        let expectedIndex = 0;
        let translateAmount = getTranslateAmount(mediaItems.length, 1, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        // Only display 1st item
        imageItems.forEach((item, index) => {
            expect(item.attributes('aria-hidden')).toBe(index === expectedIndex ? undefined : 'true');
        });

        // Show border around 1st item
        thumbnailItems.forEach((item, index) => {
            expect(item.classes('is--active')).toBe(index === expectedIndex);
        });

        // Click on 2nd item
        expectedIndex = 1;
        await thumbnailItems.at(expectedIndex).trigger('click');

        // Move to 2nd item, translate 20% to the left
        translateAmount = getTranslateAmount(mediaItems.length, 1, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        imageItems.forEach((item, index) => {
            expect(item.attributes('aria-hidden')).toBe(index === expectedIndex ? undefined : 'true');
        });

        thumbnailItems.forEach((item, index) => {
            expect(item.classes('is--active')).toBe(index === expectedIndex);
        });

        // Click on 4th item
        expectedIndex = 3;
        await thumbnailItems.at(expectedIndex).trigger('click');

        // Move to 4th item, translate 60% to the left
        translateAmount = getTranslateAmount(mediaItems.length, 1, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        // Only display 4th item
        imageItems.forEach((item, index) => {
            expect(item.attributes('aria-hidden')).toBe(index === expectedIndex ? undefined : 'true');
        });

        // Show border around 4th item
        thumbnailItems.forEach((item, index) => {
            expect(item.classes('is--active')).toBe(index === expectedIndex);
        });
    });

    it('should set active thumbnail item correctly when navigating image slider', async () => {
        const wrapper = await createWrapper();
        const thumbnailItems = wrapper.findAll(
            '.sw-image-preview-modal__thumbnail-slider .sw-image-slider__element-container',
        );
        const imageItems = wrapper.findAll('.sw-image-preview-modal__image-slider .sw-image-slider__element-wrapper');

        const arrowLeft = wrapper.find('.sw-image-preview-modal__image-slider .arrow-left');
        const arrowRight = wrapper.find('.sw-image-preview-modal__image-slider .arrow-right');

        let expectedIndex = 0;
        imageItems.forEach((item, index) => {
            expect(item.attributes('aria-hidden')).toBe(index === expectedIndex ? undefined : 'true');
        });

        thumbnailItems.forEach((item, index) => {
            expect(item.classes('is--active')).toBe(index === expectedIndex);
        });

        // Move to 2nd item
        await arrowRight.trigger('click');

        expectedIndex = 1;
        imageItems.forEach((item, index) => {
            expect(item.attributes('aria-hidden')).toBe(index === expectedIndex ? undefined : 'true');
        });

        thumbnailItems.forEach((item, index) => {
            expect(item.classes('is--active')).toBe(index === expectedIndex);
        });

        // Move to 5th item
        await arrowRight.trigger('click');
        await arrowRight.trigger('click');
        await arrowRight.trigger('click');

        expectedIndex = 4;
        imageItems.forEach((item, index) => {
            expect(item.attributes('aria-hidden')).toBe(index === expectedIndex ? undefined : 'true');
        });

        thumbnailItems.forEach((item, index) => {
            expect(item.classes('is--active')).toBe(index === expectedIndex);
        });

        // Move to 3rd item
        await arrowLeft.trigger('click');
        await arrowLeft.trigger('click');

        expectedIndex = 2;
        imageItems.forEach((item, index) => {
            expect(item.attributes('aria-hidden')).toBe(index === expectedIndex ? undefined : 'true');
        });

        thumbnailItems.forEach((item, index) => {
            expect(item.classes('is--active')).toBe(index === expectedIndex);
        });
    });

    it('should set active item base on initial activeItemId', async () => {
        const wrapper = await createWrapper({
            activeItemId: '2',
        });

        const expectedIndex = 2;
        const thumbnailItems = wrapper.findAll(
            '.sw-image-preview-modal__thumbnail-slider .sw-image-slider__element-container',
        );
        const imageItems = wrapper.findAll('.sw-image-preview-modal__image-slider .sw-image-slider__element-wrapper');

        imageItems.forEach((item, index) => {
            expect(item.attributes('aria-hidden')).toBe(index === expectedIndex ? undefined : 'true');
        });

        thumbnailItems.forEach((item, index) => {
            expect(item.classes('is--active')).toBe(index === expectedIndex);
        });
    });

    it('should able to click Zoom In button', async () => {
        const wrapper = await createWrapper();
        const image = wrapper.find('.sw-image-preview-modal__image-slider .sw-image-slider__element-image.is--active');
        const staticStyles = 'object-fit: contain; transition: all 350ms ease 0s;';

        wrapper.vm.getActiveImage = jest.fn().mockImplementation(() => Promise.resolve());

        // Mock image with natural size bigger than offset size
        wrapper.vm.image = createImage(wrapper.vm.image, zoomableImage);

        wrapper.vm.imageSliderMounted = false;
        await wrapper.vm.afterComponentsMounted();

        await flushPromises();

        const btnZoomIn = wrapper.find({ ref: 'btnZoomIn' });
        const btnZoomOut = wrapper.find({ ref: 'btnZoomOut' });
        const btnReset = wrapper.find({ ref: 'btnReset' });

        /* Initial states of buttons after updating image,
            zoom in button is available since its natural size is bigger than offset sizes
        */
        expect(btnZoomIn.attributes('disabled')).toBeUndefined();
        expect(btnZoomOut.attributes('disabled')).toBe('');
        expect(btnReset.attributes('disabled')).toBe('');

        // Click on zoom in button
        await btnZoomIn.trigger('click');
        await flushPromises();

        // The image is not zoomed in to max value
        expect(btnZoomIn.attributes('disabled')).toBeUndefined();
        expect(btnZoomOut.attributes('disabled')).toBeUndefined();
        expect(btnReset.attributes('disabled')).toBeUndefined();

        expect(image.attributes('style')).toContain(`${staticStyles} transform: scale(${wrapper.vm.scale});`);

        // Further click on zoom in button
        await btnZoomIn.trigger('click');
        await flushPromises();

        // The image is zoomed in to max value
        expect(btnZoomIn.attributes('disabled')).toBe('');
        expect(btnZoomOut.attributes('disabled')).toBeUndefined();
        expect(btnReset.attributes('disabled')).toBeUndefined();

        expect(image.attributes('style')).toContain(`${staticStyles} transform: scale(${wrapper.vm.maxZoomValue});`);

        wrapper.vm.getActiveImage.mockReset();
    });

    it('should able to click Zoom Out button', async () => {
        const wrapper = await createWrapper();
        const btnZoomIn = wrapper.find({ ref: 'btnZoomIn' });
        const btnZoomOut = wrapper.find({ ref: 'btnZoomOut' });
        const btnReset = wrapper.find({ ref: 'btnReset' });
        const image = wrapper.find('.sw-image-preview-modal__image-slider .sw-image-slider__element-image.is--active');
        const staticStyles = 'object-fit: contain; transition: all 350ms ease 0s;';

        wrapper.vm.getActiveImage = jest.fn().mockImplementation(() => Promise.resolve());

        // Mock image with natural size bigger than offset size
        wrapper.vm.image = createImage(wrapper.vm.image, zoomableImage);

        wrapper.vm.imageSliderMounted = false;
        await wrapper.vm.afterComponentsMounted();

        await flushPromises();

        /* Initial states of buttons after updating image,
            zoom in button is available since its natural size is bigger than offset sizes
        */
        expect(btnZoomIn.attributes('disabled')).toBeUndefined();
        expect(btnZoomOut.attributes('disabled')).toBe('');
        expect(btnReset.attributes('disabled')).toBe('');

        // Click on zoom in button to max value
        await btnZoomIn.trigger('click');
        await btnZoomIn.trigger('click');

        await flushPromises();

        // The image is zoomed in to max value
        expect(btnZoomIn.attributes('disabled')).toBe('');
        expect(btnZoomOut.attributes('disabled')).toBeUndefined();
        expect(btnReset.attributes('disabled')).toBeUndefined();

        expect(image.attributes('style')).toContain(`${staticStyles} transform: scale(${wrapper.vm.maxZoomValue});`);

        // Click on zoom out button
        await btnZoomOut.trigger('click');
        // The image is not zoomed in to max value
        expect(btnZoomIn.attributes('disabled')).toBeUndefined();
        expect(btnZoomOut.attributes('disabled')).toBeUndefined();
        expect(btnReset.attributes('disabled')).toBeUndefined();

        expect(image.attributes('style')).toContain(`${staticStyles} transform: scale(${wrapper.vm.scale});`);

        // Further click on zoom out button
        await btnZoomOut.trigger('click');
        // The image is zoomed out to offset value
        expect(btnZoomIn.attributes('disabled')).toBeUndefined();
        expect(btnZoomOut.attributes('disabled')).toBe('');
        expect(btnReset.attributes('disabled')).toBe('');

        expect(image.attributes('style')).toContain(`${staticStyles} transform: scale(1);`);

        wrapper.vm.getActiveImage.mockReset();
    });

    it('should able to click Reset button', async () => {
        const wrapper = await createWrapper();
        const btnZoomIn = wrapper.find({ ref: 'btnZoomIn' });
        const btnZoomOut = wrapper.find({ ref: 'btnZoomOut' });
        const btnReset = wrapper.find({ ref: 'btnReset' });
        const image = wrapper.find('.sw-image-preview-modal__image-slider .sw-image-slider__element-image.is--active');
        const staticStyles = 'object-fit: contain; transition: all 350ms ease 0s;';

        wrapper.vm.getActiveImage = jest.fn().mockImplementation(() => Promise.resolve());

        // Mock image with natural size bigger than offset size
        wrapper.vm.image = createImage(wrapper.vm.image, zoomableImage);

        wrapper.vm.imageSliderMounted = false;
        await wrapper.vm.afterComponentsMounted();

        await flushPromises();

        /* Initial states of buttons after updating image,
            zoom in button is available since its natural size is bigger than offset sizes
        */
        expect(btnZoomIn.attributes('disabled')).toBeUndefined();
        expect(btnZoomOut.attributes('disabled')).toBe('');
        expect(btnReset.attributes('disabled')).toBe('');

        // Click on zoom in button to max value
        await btnZoomIn.trigger('click');
        await btnZoomIn.trigger('click');

        // The image is zoomed in to max value
        expect(btnZoomIn.attributes('disabled')).toBe('');
        expect(btnZoomOut.attributes('disabled')).toBeFalsy();
        expect(btnReset.attributes('disabled')).toBeFalsy();

        expect(image.attributes('style')).toContain(`${staticStyles} transform: scale(${wrapper.vm.maxZoomValue});`);

        // Click on zoom reset button
        await btnReset.trigger('click');
        // The image is reseted to initial offset size
        expect(btnZoomIn.attributes('disabled')).toBeUndefined();
        expect(btnZoomOut.attributes('disabled')).toBe('');
        expect(btnReset.attributes('disabled')).toBe('');

        expect(image.attributes('style')).toContain(`${staticStyles} transform: scale(1);`);

        wrapper.vm.getActiveImage.mockReset();
    });

    it('should able to zoom image with mouse wheel', async () => {
        const wrapper = await createWrapper();
        const btnZoomIn = wrapper.find({ ref: 'btnZoomIn' });
        const btnZoomOut = wrapper.find({ ref: 'btnZoomOut' });
        const btnReset = wrapper.find({ ref: 'btnReset' });

        const image = wrapper.find('.sw-image-preview-modal__image-slider .sw-image-slider__element-image.is--active');
        const staticStyles = 'object-fit: contain; transition: all 350ms ease 0s;';

        wrapper.vm.getActiveImage = jest.fn().mockImplementation(() => Promise.resolve());

        // Mock image with natural size bigger than offset size
        wrapper.vm.image = createImage(wrapper.vm.image, zoomableImage);

        wrapper.vm.imageSliderMounted = false;
        await wrapper.vm.afterComponentsMounted();

        await flushPromises();

        /* Initial states of buttons after updating image,
            zoom in button is available since its natural size is bigger than offset sizes
        */
        expect(btnZoomIn.attributes('disabled')).toBeUndefined();
        expect(btnZoomOut.attributes('disabled')).toBe('');
        expect(btnReset.attributes('disabled')).toBe('');

        // Wheel down to zoom
        await wrapper.trigger('wheel', { wheelDelta: 200 });

        expect(btnZoomIn.attributes('disabled')).toBeUndefined();
        expect(btnZoomOut.attributes('disabled')).toBeFalsy();
        expect(btnReset.attributes('disabled')).toBeFalsy();

        expect(image.attributes('style')).toContain(`${staticStyles} transform: scale(${wrapper.vm.scale});`);

        // Wheel down to max value
        await wrapper.trigger('wheel', { wheelDelta: 600 });

        expect(btnZoomIn.attributes('disabled')).toBe('');
        expect(btnZoomOut.attributes('disabled')).toBeFalsy();
        expect(btnReset.attributes('disabled')).toBeFalsy();

        expect(image.attributes('style')).toContain(`${staticStyles} transform: scale(${wrapper.vm.maxZoomValue});`);

        // Further wheel down
        await wrapper.trigger('wheel', { wheelDelta: 2000 });

        expect(btnZoomIn.attributes('disabled')).toBe('');
        expect(btnZoomOut.attributes('disabled')).toBeFalsy();
        expect(btnReset.attributes('disabled')).toBeFalsy();

        expect(image.attributes('style')).toContain(`${staticStyles} transform: scale(${wrapper.vm.maxZoomValue});`);

        // Wheel up a bit
        await wrapper.trigger('wheel', { wheelDelta: -300 });

        // The image is reseted to initial offset size
        expect(btnZoomIn.attributes('disabled')).toBeUndefined();
        expect(btnZoomOut.attributes('disabled')).toBeFalsy();
        expect(btnReset.attributes('disabled')).toBeFalsy();

        expect(image.attributes('style')).toContain(`${staticStyles} transform: scale(${wrapper.vm.scale});`);

        // Further wheel up
        await wrapper.trigger('wheel', { wheelDelta: -2000 });

        // The image is reseted to initial offset size
        expect(btnZoomIn.attributes('disabled')).toBeUndefined();
        expect(btnZoomOut.attributes('disabled')).toBe('');
        expect(btnReset.attributes('disabled')).toBe('');

        expect(image.attributes('style')).toContain(`${staticStyles} transform: scale(1);`);

        wrapper.vm.getActiveImage.mockReset();
    });

    it('should update button states correctly when image is updated', async () => {
        const wrapper = await createWrapper();
        const btnZoomIn = wrapper.find({ ref: 'btnZoomIn' });
        const btnZoomOut = wrapper.find({ ref: 'btnZoomOut' });
        const btnReset = wrapper.find({ ref: 'btnReset' });

        wrapper.vm.getActiveImage = jest.fn().mockImplementation(() => Promise.resolve());

        // Mock image with natural size smaller than offset size
        await wrapper.setData({
            image: createImage(wrapper.vm.image, unzoomableImage),
        });

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        expect(btnZoomIn.attributes('disabled')).toBe('');
        expect(btnZoomOut.attributes('disabled')).toBe('');
        expect(btnReset.attributes('disabled')).toBe('');
    });
});
