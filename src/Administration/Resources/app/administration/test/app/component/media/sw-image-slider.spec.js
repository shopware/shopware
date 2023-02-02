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

function createWrapper(propsData = {}, listeners = {}) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-image-slider'), {
        localVue,
        stubs: {
            'sw-icon': true
        },
        provide: {
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

describe('src/app/component/media/sw-image-slider', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should display every image, even in an object, independent if the link is external or not', () => {
        const wrapper = createWrapper();
        const containerScrollable = wrapper.find('.sw-image-slider__image-scrollable');
        const actualImages = wrapper.findAll(
            '.sw-image-slider__image-scrollable .sw-image-slider__element-image'
        );

        expect(containerScrollable.exists()).toBeTruthy();
        expect(actualImages.length).toBe(images.length);
        expect(actualImages.at(1).attributes().src).toBe(images[1]);
    });

    it('should display descriptions, if enabled and existing', () => {
        const wrapper = createWrapper();
        const expectedAmountOfDescriptions = images.filter((image) => {
            return typeof image === 'object' && image.description && image.description.length >= 1;
        }).length;

        const actualDescriptions = wrapper.findAll(
            '.sw-image-slider__image-scrollable .sw-image-slider__element-description'
        );

        expect(actualDescriptions.length).toBe(expectedAmountOfDescriptions);
        expect(actualDescriptions.at(0).text()).toContain(images[0].description);
    });

    it('should not display descriptions, even if existing', () => {
        const wrapper = createWrapper({ enableDescriptions: false });

        const actualDescriptions = wrapper.findAll(
            '.sw-image-slider__image-scrollable .sw-image-slider__element-description'
        );

        expect(actualDescriptions.length).toBe(0);
    });

    it('should navigate using the arrows', async () => {
        const wrapper = createWrapper();
        const data = wrapper.vm._data;
        const itemPerPage = wrapper.vm.itemPerPage;
        const imageLength = wrapper.vm.images.length;

        const arrowLeft = wrapper.find('.arrow-left');
        const arrowRight = wrapper.find('.arrow-right');
        const containerScrollable = wrapper.find('.sw-image-slider__image-scrollable');

        const staticStyles = 'width: 500%; gap: 20px;';

        expect(arrowLeft.exists()).toBeTruthy();
        expect(arrowRight.exists()).toBeTruthy();

        // Currently at the first image
        let expectedIndex = 0;
        let translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(data.currentPageNumber).toBe(expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        // First ArrowRight click
        await arrowRight.trigger('click');
        expectedIndex = 1;
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        // First ArrowLeft click
        await arrowLeft.trigger('click');
        expectedIndex = 0;
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        // Check if it doesnt exceed its range to the left
        arrowLeft.trigger('click');
        await arrowLeft.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        // Click a bit further to the right
        expectedIndex = 4;
        arrowRight.trigger('click');
        arrowRight.trigger('click');
        arrowRight.trigger('click');
        await arrowRight.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        // Check if it doesnt exceed its range to the right
        arrowRight.trigger('click');
        await arrowRight.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);
    });

    it('should navigate using the buttons', async () => {
        const wrapper = createWrapper();
        const data = wrapper.vm._data;
        const itemPerPage = wrapper.vm.itemPerPage;
        const imageLength = wrapper.vm.images.length;

        const buttons = wrapper.findAll('.sw-image-slider__buttons-element');
        const containerScrollable = wrapper.find('.sw-image-slider__image-scrollable');

        const staticStyles = 'width: 500%; gap: 20px;';

        expect(buttons.length).toBe(5);

        let expectedIndex = 0;
        let translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(data.currentPageNumber).toBe(0);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        expectedIndex = 3;
        await buttons.at(expectedIndex).trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        expectedIndex = 1;
        await buttons.at(expectedIndex).trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);
    });

    it('should navigate by arrows correctly when there are more than 1 item per page', async () => {
        const wrapper = createWrapper({
            itemPerPage: 2
        });

        const data = wrapper.vm._data;
        const itemPerPage = wrapper.vm.itemPerPage;
        const imageLength = wrapper.vm.images.length;

        const arrowLeft = wrapper.find('.arrow-left');
        const arrowRight = wrapper.find('.arrow-right');
        const containerScrollable = wrapper.find('.sw-image-slider__image-scrollable');

        const staticStyles = 'width: 250%; gap: 20px;';

        expect(arrowLeft.exists()).toBeTruthy();
        expect(arrowRight.exists()).toBeTruthy();

        // Currently at the first image
        let expectedIndex = 0;
        let translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(data.currentPageNumber).toBe(expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        // First ArrowRight click
        await arrowRight.trigger('click');
        expectedIndex = 1;
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        // First ArrowLeft click
        await arrowLeft.trigger('click');
        expectedIndex = 0;
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        // Check if it doesnt exceed its range to the left
        arrowLeft.trigger('click');
        await arrowLeft.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        // Click a bit further to the right
        expectedIndex = 2;
        arrowRight.trigger('click');
        await arrowRight.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        // Check if it doesnt exceed its range to the right
        arrowRight.trigger('click');
        await arrowRight.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);
    });

    it('should navigate by buttons correctly when there are more than 1 item per page', async () => {
        const wrapper = createWrapper({
            itemPerPage: 2
        });

        const data = wrapper.vm._data;
        const itemPerPage = wrapper.vm.itemPerPage;
        const imageLength = wrapper.vm.images.length;

        const buttons = wrapper.findAll('.sw-image-slider__buttons-element');
        const containerScrollable = wrapper.find('.sw-image-slider__image-scrollable');

        const staticStyles = 'width: 250%; gap: 20px;';

        expect(buttons.length).toBe(3);

        // Move to 1st page which contain 1st and 2nd images
        let expectedIndex = 0;
        let translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(data.currentPageNumber).toBe(0);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        // Move to last page which contain 4th and 5th images
        expectedIndex = 2;
        await buttons.at(expectedIndex).trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);

        // Move to 2nd page which contain 3rd and 4th images
        expectedIndex = 1;
        await buttons.at(expectedIndex).trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style)
            .toContain(`${staticStyles} transform: translateX(-${translateAmount}%);`);
    });

    it('should mark aria-hidden correctly when navigating', async () => {
        const wrapper = createWrapper({
            itemPerPage: 2
        });

        const buttons = wrapper.findAll('.sw-image-slider__buttons-element');
        expect(buttons.length).toBe(3);

        // Move to 1st page, mark 1st and 2nd images not hidden
        let expectedIndex;
        const imageWrappers = wrapper.findAll('.sw-image-slider__element-wrapper');
        imageWrappers.wrappers.forEach((item, index) => {
            if (index === 0 || index === 1) {
                expect(item.attributes()['aria-hidden']).toBeFalsy();
            } else {
                expect(item.attributes()['aria-hidden']).toBeTruthy();
            }
        });

        // Move to last page, mark 4th and 5th images not hidden
        expectedIndex = 2;
        await buttons.at(expectedIndex).trigger('click');

        imageWrappers.wrappers.forEach((item, index) => {
            if (index === 3 || index === 4) {
                expect(item.attributes()['aria-hidden']).toBeFalsy();
            } else {
                expect(item.attributes()['aria-hidden']).toBeTruthy();
            }
        });

        // Move to 2nd page, mark 3rd and 4th images not hidden
        expectedIndex = 1;
        await buttons.at(expectedIndex).trigger('click');

        imageWrappers.wrappers.forEach((item, index) => {
            if (index === 2 || index === 3) {
                expect(item.attributes()['aria-hidden']).toBeFalsy();
            } else {
                expect(item.attributes()['aria-hidden']).toBeTruthy();
            }
        });
    });

    it('should show active border around item after clicking on it', async () => {
        const wrapper = createWrapper({
            itemPerPage: 5
        });

        let expectedIndex = 0;
        const imageContainers = wrapper.findAll('.sw-image-slider__element-container');

        imageContainers.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.classes()).toContain('is--active');
            } else {
                expect(item.classes()).not.toContain('is--active');
            }
        });

        expectedIndex = 1;
        await imageContainers.at(expectedIndex).trigger('click');

        imageContainers.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.classes()).toContain('is--active');
            } else {
                expect(item.classes()).not.toContain('is--active');
            }
        });

        expectedIndex = 3;
        await imageContainers.at(expectedIndex).trigger('click');

        imageContainers.wrappers.forEach((item, index) => {
            if (index === expectedIndex) {
                expect(item.classes()).toContain('is--active');
            } else {
                expect(item.classes()).not.toContain('is--active');
            }
        });
    });

    it('should navigate back to first page by next arrow or last page by prev arrow when rewind is active', async () => {
        const wrapper = createWrapper({
            itemPerPage: 2,
            rewind: true
        });

        const data = wrapper.vm._data;
        const arrowLeft = wrapper.find('.arrow-left');
        const arrowRight = wrapper.find('.arrow-right');

        // Currently at the first image
        let expectedIndex = 0;

        // First ArrowRight click
        await arrowRight.trigger('click');
        expectedIndex = 1;
        expect(data.currentPageNumber).toBe(expectedIndex);

        // Click a bit further to the right and check if it go back to first page
        expectedIndex = 0;
        arrowRight.trigger('click');
        await arrowRight.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        // Check if it go to the last page
        expectedIndex = 2;
        await arrowLeft.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);
    });
});
