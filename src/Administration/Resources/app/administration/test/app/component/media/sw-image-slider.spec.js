import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/media/sw-image-slider';

const images = [
    {
        src: '/administration/static/img/dashboard-logo.svg',
        description: 'Some really awesome and totally useful description.'
    },
    'https://via.placeholder.com/218x229?text=Placeholder1',
    {
        src: 'https://via.placeholder.com/218x229?text=Placeholder2'
    },
    '/administration/static/img/plugin-manager--login.png',
    '/administration/static/img/sw-login-background.png'
];

function createWrapper(propsData = {}, listeners = {}) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-image-slider'), {
        localVue,
        stubs: {
            'sw-icon': true
        },
        provide: {
        },
        mocks: {
            $tc: v => v
        },
        listeners,
        propsData: {
            ...{
                canvasWidth: 218,
                canvasHeight: 229,
                enableDescriptions: true,
                navigationType: 'all',
                images
            },
            ...propsData
        }
    });
}

describe('src/app/component/base/sw-image-slider', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should display every image, even in an object, independent if the link is external or not', () => {
        const wrapper = createWrapper();
        const containerScrollable = wrapper.find('.sw-image-slider__image-container-scrollable');
        const elementWrappers = wrapper.findAll(
            '.sw-image-slider__image-container-scrollable > .sw-image-slider__image-container-element-wrapper'
        );

        expect(containerScrollable.exists()).toBeTruthy();
        expect(elementWrappers.length).toBe(images.length);
    });

    it('should display descriptions, if enabled and existing', () => {
        const wrapper = createWrapper();
        const expectedAmountOfDescriptions = images.filter((image) => {
            return typeof image === 'object' && image.description && image.description.length >= 1;
        }).length;

        const actualDescriptions = wrapper.findAll(
            '.sw-image-slider__image-container-scrollable .sw-image-slider__image-container-element-description'
        );

        expect(actualDescriptions.length).toBe(expectedAmountOfDescriptions);
        expect(actualDescriptions.at(0).text()).toContain(images[0].description);
    });

    it('should not display descriptions, even if existing', () => {
        const wrapper = createWrapper({ enableDescriptions: false });

        const actualDescriptions = wrapper.findAll(
            '.sw-image-slider__image-container-scrollable .sw-image-slider__image-container-element-description'
        );

        expect(actualDescriptions.length).toBe(0);
    });

    it('should navigate using the arrows', async () => {
        const wrapper = createWrapper();
        const data = wrapper.vm._data;

        const arrowLeft = wrapper.find('.arrow-left');
        const arrowRight = wrapper.find('.arrow-right');
        const containerScrollable = wrapper.find('.sw-image-slider__image-container-scrollable');

        const staticStyles = 'width: 1170px; gap: 20px;';

        expect(arrowLeft.exists()).toBeTruthy();
        expect(arrowRight.exists()).toBeTruthy();

        // Currently at the first image
        let expectedIndex = 0;
        expect(data.currentPageNumber).toBe(expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${expectedIndex * 238}px);`);

        // First ArrowRight click
        await arrowRight.trigger('click');
        expectedIndex = 1;
        expect(data.currentPageNumber).toBe(expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${expectedIndex * 238}px);`);

        // First ArrowLeft click
        await arrowLeft.trigger('click');
        expectedIndex = 0;
        expect(data.currentPageNumber).toBe(expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${expectedIndex * 238}px);`);

        // Check if it doesnt exceed its range to the left
        arrowLeft.trigger('click');
        await arrowLeft.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${expectedIndex * 238}px);`);

        // Click a bit further to the right
        expectedIndex = 4;
        arrowRight.trigger('click');
        arrowRight.trigger('click');
        arrowRight.trigger('click');
        await arrowRight.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${expectedIndex * 238}px);`);

        // Check if it doesnt exceed its range to the right
        arrowRight.trigger('click');
        await arrowRight.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${expectedIndex * 238}px);`);
    });

    it('should navigate using the buttons', async () => {
        const wrapper = createWrapper();
        const data = wrapper.vm._data;

        const buttons = wrapper.findAll('.sw-image-slider__buttons-element');
        const containerScrollable = wrapper.find('.sw-image-slider__image-container-scrollable');

        const staticStyles = 'width: 1170px; gap: 20px;';

        expect(buttons.length).toBe(5);

        let expectedIndex = 0;
        expect(data.currentPageNumber).toBe(0);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${expectedIndex * 238}px);`);

        expectedIndex = 3;
        await buttons.at(expectedIndex).trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${expectedIndex * 238}px);`);

        expectedIndex = 1;
        await buttons.at(expectedIndex).trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${expectedIndex * 238}px);`);
    });
});
