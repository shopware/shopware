import { shallowMount, enableAutoDestroy } from '@vue/test-utils';
import 'src/app/component/modal/sw-image-preview-modal';
import 'src/app/component/media/sw-image-slider';

const mediaItems = [
    {
        id: '0',
        media: { url: '/administration/static/img/dashboard-logo.svg' }
    },
    {
        id: '1',
        media: { url: 'https://via.placeholder.com/218x229?text=Placeholder1' }
    },
    {
        id: '2',
        media: { url: 'https://via.placeholder.com/218x229?text=Placeholder2' }
    },
    {
        id: '3',
        media: { url: '/administration/static/img/plugin-manager--login.png' }
    },
    {
        id: '4',
        media: { url: '/administration/static/img/sw-login-background.png' }
    }
];

const zoomableImage = {
    naturalWidth: 500,
    naturalHeight: 300,
    offsetWidth: 400,
    offsetHeight: 200
};

const unzoomableImage = {
    naturalWidth: 400,
    naturalHeight: 200,
    offsetWidth: 400,
    offsetHeight: 200
};

function getTranslateAmount(itemLength = 1, itemPerPage = 1, expectedIndex = 0) {
    const remainder = itemLength % itemPerPage;
    const totalPage = Math.ceil(itemLength / itemPerPage);

    if (itemPerPage === 1
        || remainder === 0
        || itemLength <= itemPerPage) {
        return expectedIndex / totalPage * 100;
    }

    const itemWidth = 100 / itemLength;
    return (expectedIndex === totalPage - 1)
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
            value: naturalWidth || 0
        },
        naturalHeight: {
            value: naturalHeight || 0
        },
        offsetWidth: {
            value: offsetWidth || 0
        },
        offsetHeight: {
            value: offsetHeight || 0
        }
    });

    return image;
}

function createWrapper(propsData = {}, listeners = {}) {
    return shallowMount(Shopware.Component.build('sw-image-preview-modal'), {
        stubs: {
            'sw-icon': true,
            'sw-image-slider': Shopware.Component.build('sw-image-slider')
        },
        listeners,
        propsData: {
            mediaItems,
            activeItemId: '0',
            ...propsData
        }
    });
}

enableAutoDestroy(afterEach);

describe('src/app/component/modal/sw-image-preview-modal', () => {
    it('should navigate image slider correctly when clicking on thumbnail item', async () => {
        const wrapper = createWrapper();
        const thumbnailItems = wrapper.findAll('.sw-image-preview-modal__thumbnail-slider .sw-image-slider__element-container');
        const imageItems = wrapper.findAll('.sw-image-preview-modal__image-slider .sw-image-slider__element-wrapper');

        const containerScrollable = wrapper.find('.sw-image-preview-modal__image-slider .sw-image-slider__image-scrollable');
        const staticStyles = 'width: 500%; gap: 20px;';

        let expectedIndex = 0;
        let translateAmount = getTranslateAmount(mediaItems.length, 1, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        // Only display 1st item
        imageItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.attributes('aria-hidden')).toBeFalsy();
            } else {
                expect(item.attributes('aria-hidden')).toBeTruthy();
            }
        });

        // Show border around 1st item
        thumbnailItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.classes()).toContain('is--active');
            } else {
                expect(item.classes()).not.toContain('is--active');
            }
        });

        // Click on 2nd item
        expectedIndex = 1;
        await thumbnailItems.at(expectedIndex).trigger('click');

        // Move to 2nd item, translate 20% to the left
        translateAmount = getTranslateAmount(mediaItems.length, 1, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        imageItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.attributes('aria-hidden')).toBeFalsy();
            } else {
                expect(item.attributes('aria-hidden')).toBeTruthy();
            }
        });

        thumbnailItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.classes()).toContain('is--active');
            } else {
                expect(item.classes()).not.toContain('is--active');
            }
        });

        // Click on 4th item
        expectedIndex = 3;
        await thumbnailItems.at(expectedIndex).trigger('click');

        // Move to 4th item, translate 60% to the left
        translateAmount = getTranslateAmount(mediaItems.length, 1, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        // Only display 4th item
        imageItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.attributes('aria-hidden')).toBeFalsy();
            } else {
                expect(item.attributes('aria-hidden')).toBeTruthy();
            }
        });

        // Show border around 4th item
        thumbnailItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.classes()).toContain('is--active');
            } else {
                expect(item.classes()).not.toContain('is--active');
            }
        });
    });

    it('should set active thumbnail item correctly when navigating image slider', async () => {
        const wrapper = createWrapper();
        const thumbnailItems = wrapper.findAll('.sw-image-preview-modal__thumbnail-slider .sw-image-slider__element-container');
        const imageItems = wrapper.findAll('.sw-image-preview-modal__image-slider .sw-image-slider__element-wrapper');

        const arrowLeft = wrapper.find('.sw-image-preview-modal__image-slider .arrow-left');
        const arrowRight = wrapper.find('.sw-image-preview-modal__image-slider .arrow-right');

        let expectedIndex = 0;
        imageItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.attributes()['aria-hidden']).toBeFalsy();
            } else {
                expect(item.attributes()['aria-hidden']).toBeTruthy();
            }
        });

        thumbnailItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.classes()).toContain('is--active');
            } else {
                expect(item.classes()).not.toContain('is--active');
            }
        });

        // Move to 2nd item
        await arrowRight.trigger('click');

        expectedIndex = 1;
        imageItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.attributes('aria-hidden')).toBeFalsy();
            } else {
                expect(item.attributes('aria-hidden')).toBeTruthy();
            }
        });

        thumbnailItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.classes()).toContain('is--active');
            } else {
                expect(item.classes()).not.toContain('is--active');
            }
        });

        // Move to 5th item
        arrowRight.trigger('click');
        arrowRight.trigger('click');
        await arrowRight.trigger('click');

        expectedIndex = 4;
        imageItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.attributes('aria-hidden')).toBeFalsy();
            } else {
                expect(item.attributes('aria-hidden')).toBeTruthy();
            }
        });

        thumbnailItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.classes()).toContain('is--active');
            } else {
                expect(item.classes()).not.toContain('is--active');
            }
        });

        // Move to 3rd item
        arrowLeft.trigger('click');
        await arrowLeft.trigger('click');

        expectedIndex = 2;
        imageItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.attributes('aria-hidden')).toBeFalsy();
            } else {
                expect(item.attributes('aria-hidden')).toBeTruthy();
            }
        });

        thumbnailItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.classes()).toContain('is--active');
            } else {
                expect(item.classes()).not.toContain('is--active');
            }
        });
    });

    it('should set active item base on initial activeItemId', async () => {
        const wrapper = createWrapper({
            activeItemId: '2'
        });

        const expectedIndex = 2;
        const thumbnailItems = wrapper.findAll('.sw-image-preview-modal__thumbnail-slider .sw-image-slider__element-container');
        const imageItems = wrapper.findAll('.sw-image-preview-modal__image-slider .sw-image-slider__element-wrapper');

        imageItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.attributes('aria-hidden')).toBeFalsy();
            } else {
                expect(item.attributes('aria-hidden')).toBeTruthy();
            }
        });

        thumbnailItems.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.classes()).toContain('is--active');
            } else {
                expect(item.classes()).not.toContain('is--active');
            }
        });
    });

    it('should able to click Zoom In button', async () => {
        const wrapper = createWrapper();
        const btnZoomIn = wrapper.findComponent({ ref: 'btnZoomIn' });
        const btnZoomOut = wrapper.findComponent({ ref: 'btnZoomOut' });
        const btnReset = wrapper.findComponent({ ref: 'btnReset' });
        const image = wrapper.find('.sw-image-preview-modal__image-slider .sw-image-slider__element-image.is--active');
        const staticStyles = 'object-fit: contain; transition: all 350ms ease 0s;';

        wrapper.vm.getActiveImage = jest.fn().mockImplementation(() => Promise.resolve());

        // Mock image with natural size bigger than offset size
        await wrapper.setData({
            image: createImage(wrapper.vm.image, zoomableImage)
        });

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        /* Initial states of buttons after updating image,
            zoom in button is available since its natural size is bigger than offset sizes
        */
        expect(btnZoomIn.attributes('disabled')).toBeFalsy();
        expect(btnZoomOut.attributes('disabled')).toBe('disabled');
        expect(btnReset.attributes('disabled')).toBe('disabled');

        // Click on zoom in button
        await btnZoomIn.trigger('click');

        // The image is not zoomed in to max value
        expect(btnZoomIn.attributes('disabled')).toBeFalsy();
        expect(btnZoomOut.attributes('disabled')).toBeFalsy();
        expect(btnReset.attributes('disabled')).toBeFalsy();

        expect(image.attributes('style'))
            .toContain(`${staticStyles} transform: scale(${wrapper.vm.scale});`);

        // Further click on zoom in button
        await btnZoomIn.trigger('click');

        // The image is zoomed in to max value
        expect(btnZoomIn.attributes('disabled')).toBe('disabled');
        expect(btnZoomOut.attributes('disabled')).toBeFalsy();
        expect(btnReset.attributes('disabled')).toBeFalsy();

        expect(image.attributes('style'))
            .toContain(`${staticStyles} transform: scale(${wrapper.vm.maxZoomValue});`);

        wrapper.vm.getActiveImage.mockReset();
    });

    it('should able to click Zoom Out button', async () => {
        const wrapper = createWrapper();
        const btnZoomIn = wrapper.findComponent({ ref: 'btnZoomIn' });
        const btnZoomOut = wrapper.findComponent({ ref: 'btnZoomOut' });
        const btnReset = wrapper.findComponent({ ref: 'btnReset' });
        const image = wrapper.find('.sw-image-preview-modal__image-slider .sw-image-slider__element-image.is--active');
        const staticStyles = 'object-fit: contain; transition: all 350ms ease 0s;';

        wrapper.vm.getActiveImage = jest.fn().mockImplementation(() => Promise.resolve());

        // Mock image with natural size bigger than offset size
        await wrapper.setData({
            image: createImage(wrapper.vm.image, zoomableImage)
        });

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        /* Initial states of buttons after updating image,
            zoom in button is available since its natural size is bigger than offset sizes
        */
        expect(btnZoomIn.attributes('disabled')).toBeFalsy();
        expect(btnZoomOut.attributes('disabled')).toBe('disabled');
        expect(btnReset.attributes('disabled')).toBe('disabled');

        // Click on zoom in button to max value
        btnZoomIn.trigger('click');
        await btnZoomIn.trigger('click');

        // The image is zoomed in to max value
        expect(btnZoomIn.attributes('disabled')).toBe('disabled');
        expect(btnZoomOut.attributes('disabled')).toBeFalsy();
        expect(btnReset.attributes('disabled')).toBeFalsy();

        expect(image.attributes('style'))
            .toContain(`${staticStyles} transform: scale(${wrapper.vm.maxZoomValue});`);


        // Click on zoom out button
        await btnZoomOut.trigger('click');
        // The image is not zoomed in to max value
        expect(btnZoomIn.attributes('disabled')).toBeFalsy();
        expect(btnZoomOut.attributes('disabled')).toBeFalsy();
        expect(btnReset.attributes('disabled')).toBeFalsy();

        expect(image.attributes('style'))
            .toContain(`${staticStyles} transform: scale(${wrapper.vm.scale});`);

        // Further click on zoom out button
        await btnZoomOut.trigger('click');
        // The image is zoomed out to offset value
        expect(btnZoomIn.attributes('disabled')).toBeFalsy();
        expect(btnZoomOut.attributes('disabled')).toBe('disabled');
        expect(btnReset.attributes('disabled')).toBe('disabled');

        expect(image.attributes('style'))
            .toContain(`${staticStyles} transform: scale(1);`);

        wrapper.vm.getActiveImage.mockReset();
    });

    it('should able to click Reset button', async () => {
        const wrapper = createWrapper();
        const btnZoomIn = wrapper.findComponent({ ref: 'btnZoomIn' });
        const btnZoomOut = wrapper.findComponent({ ref: 'btnZoomOut' });
        const btnReset = wrapper.findComponent({ ref: 'btnReset' });
        const image = wrapper.find('.sw-image-preview-modal__image-slider .sw-image-slider__element-image.is--active');
        const staticStyles = 'object-fit: contain; transition: all 350ms ease 0s;';

        wrapper.vm.getActiveImage = jest.fn().mockImplementation(() => Promise.resolve());

        // Mock image with natural size bigger than offset size
        await wrapper.setData({
            image: createImage(wrapper.vm.image, zoomableImage)
        });

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        /* Initial states of buttons after updating image,
            zoom in button is available since its natural size is bigger than offset sizes
        */
        expect(btnZoomIn.attributes('disabled')).toBeFalsy();
        expect(btnZoomOut.attributes('disabled')).toBe('disabled');
        expect(btnReset.attributes('disabled')).toBe('disabled');

        // Click on zoom in button to max value
        btnZoomIn.trigger('click');
        await btnZoomIn.trigger('click');

        // The image is zoomed in to max value
        expect(btnZoomIn.attributes('disabled')).toBe('disabled');
        expect(btnZoomOut.attributes('disabled')).toBeFalsy();
        expect(btnReset.attributes('disabled')).toBeFalsy();

        expect(image.attributes('style'))
            .toContain(`${staticStyles} transform: scale(${wrapper.vm.maxZoomValue});`);

        // Click on zoom reset button
        await btnReset.trigger('click');
        // The image is reseted to initial offset size
        expect(btnZoomIn.attributes('disabled')).toBeFalsy();
        expect(btnZoomOut.attributes('disabled')).toBe('disabled');
        expect(btnReset.attributes('disabled')).toBe('disabled');

        expect(image.attributes('style'))
            .toContain(`${staticStyles} transform: scale(1);`);

        wrapper.vm.getActiveImage.mockReset();
    });

    it('should able to zoom image with mouse wheel', async () => {
        const wrapper = createWrapper();
        const btnZoomIn = wrapper.findComponent({ ref: 'btnZoomIn' });
        const btnZoomOut = wrapper.findComponent({ ref: 'btnZoomOut' });
        const btnReset = wrapper.findComponent({ ref: 'btnReset' });

        const image = wrapper.find('.sw-image-preview-modal__image-slider .sw-image-slider__element-image.is--active');
        const staticStyles = 'object-fit: contain; transition: all 350ms ease 0s;';

        wrapper.vm.getActiveImage = jest.fn().mockImplementation(() => Promise.resolve());

        // Mock image with natural size bigger than offset size
        await wrapper.setData({
            image: createImage(wrapper.vm.image, zoomableImage)
        });

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        /* Initial states of buttons after updating image,
            zoom in button is available since its natural size is bigger than offset sizes
        */
        expect(btnZoomIn.attributes('disabled')).toBeFalsy();
        expect(btnZoomOut.attributes('disabled')).toBe('disabled');
        expect(btnReset.attributes('disabled')).toBe('disabled');

        // Wheel down to zoom
        await wrapper.trigger('wheel', { wheelDelta: 200 });

        expect(btnZoomIn.attributes('disabled')).toBeFalsy();
        expect(btnZoomOut.attributes('disabled')).toBeFalsy();
        expect(btnReset.attributes('disabled')).toBeFalsy();

        expect(image.attributes('style'))
            .toContain(`${staticStyles} transform: scale(${wrapper.vm.scale});`);


        // Wheel down to max value
        await wrapper.trigger('wheel', { wheelDelta: 600 });

        expect(btnZoomIn.attributes('disabled')).toBe('disabled');
        expect(btnZoomOut.attributes('disabled')).toBeFalsy();
        expect(btnReset.attributes('disabled')).toBeFalsy();

        expect(image.attributes('style'))
            .toContain(`${staticStyles} transform: scale(${wrapper.vm.maxZoomValue});`);

        // Further wheel down
        await wrapper.trigger('wheel', { wheelDelta: 2000 });

        expect(btnZoomIn.attributes('disabled')).toBe('disabled');
        expect(btnZoomOut.attributes('disabled')).toBeFalsy();
        expect(btnReset.attributes('disabled')).toBeFalsy();

        expect(image.attributes('style'))
            .toContain(`${staticStyles} transform: scale(${wrapper.vm.maxZoomValue});`);

        // Wheel up a bit
        await wrapper.trigger('wheel', { wheelDelta: -300 });

        // The image is reseted to initial offset size
        expect(btnZoomIn.attributes('disabled')).toBeFalsy();
        expect(btnZoomOut.attributes('disabled')).toBeFalsy();
        expect(btnReset.attributes('disabled')).toBeFalsy();

        expect(image.attributes('style'))
            .toContain(`${staticStyles} transform: scale(${wrapper.vm.scale});`);

        // Further wheel up
        await wrapper.trigger('wheel', { wheelDelta: -2000 });

        // The image is reseted to initial offset size
        expect(btnZoomIn.attributes('disabled')).toBeFalsy();
        expect(btnZoomOut.attributes('disabled')).toBe('disabled');
        expect(btnReset.attributes('disabled')).toBe('disabled');

        expect(image.attributes('style'))
            .toContain(`${staticStyles} transform: scale(1);`);

        wrapper.vm.getActiveImage.mockReset();
    });

    it('should update button states correctly when image is updated', async () => {
        const wrapper = createWrapper();
        const btnZoomIn = wrapper.findComponent({ ref: 'btnZoomIn' });
        const btnZoomOut = wrapper.findComponent({ ref: 'btnZoomOut' });
        const btnReset = wrapper.findComponent({ ref: 'btnReset' });

        wrapper.vm.getActiveImage = jest.fn().mockImplementation(() => Promise.resolve());

        // Mock image with natural size smaller than offset size
        await wrapper.setData({
            image: createImage(wrapper.vm.image, unzoomableImage)
        });

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        expect(btnZoomIn.attributes('disabled')).toBe('disabled');
        expect(btnZoomOut.attributes('disabled')).toBe('disabled');
        expect(btnReset.attributes('disabled')).toBe('disabled');
    });
});
