/**
 * @package content
 */
import { mount } from '@vue/test-utils';

const images = [
    {
        src: '/administration/static/img/dashboard-logo.svg',
        description: 'Some really awesome and totally useful description.',
    },
    'https://via.placeholder.com/218x229?text=Placeholder1',
    {
        src: 'https://via.placeholder.com/218x229?text=Placeholder2',
    },
    '/administration/static/img/plugin-manager--login.png',
    '/administration/static/img/sw-login-background.png',
];

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

async function createWrapper(additionalProps = {}) {
    return mount(await wrapTestComponent('sw-image-slider', { sync: true }), {
        props: {
            ...{
                canvasWidth: 218,
                canvasHeight: 229,
                enableDescriptions: true,
                navigationType: 'all',
                images,
            },
            ...additionalProps,
        },
        global: {
            stubs: {
                'sw-icon': true,
            },
        },
    });
}

describe('src/app/component/media/sw-image-slider', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should display every image, even in an object, independent if the link is external or not', async () => {
        const wrapper = await createWrapper();
        const containerScrollable = wrapper.find('.sw-image-slider__image-scrollable');
        const actualImages = wrapper.findAll('.sw-image-slider__image-scrollable .sw-image-slider__element-image');

        expect(containerScrollable.exists()).toBeTruthy();
        expect(actualImages).toHaveLength(images.length);
        expect(actualImages.at(1).attributes().src).toBe(images[1]);
    });

    it('should display descriptions, if enabled and existing', async () => {
        const wrapper = await createWrapper();
        const expectedAmountOfDescriptions = images.filter((image) => {
            return typeof image === 'object' && image.description && image.description.length >= 1;
        }).length;

        const actualDescriptions = wrapper.findAll(
            '.sw-image-slider__image-scrollable .sw-image-slider__element-description',
        );

        expect(actualDescriptions).toHaveLength(expectedAmountOfDescriptions);
        expect(actualDescriptions.at(0).text()).toContain(images[0].description);
    });

    it('should not display descriptions, even if existing', async () => {
        const wrapper = await createWrapper({ enableDescriptions: false });

        const actualDescriptions = wrapper.findAll(
            '.sw-image-slider__image-scrollable .sw-image-slider__element-description',
        );

        expect(actualDescriptions).toHaveLength(0);
    });

    it('should navigate using the arrows', async () => {
        const wrapper = await createWrapper();
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
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        // First ArrowRight click
        await arrowRight.trigger('click');
        expectedIndex = 1;
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        // First ArrowLeft click
        await arrowLeft.trigger('click');
        expectedIndex = 0;
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        // Check if it doesnt exceed its range to the left
        await arrowLeft.trigger('click');
        await arrowLeft.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        // Click a bit further to the right
        expectedIndex = 4;
        await arrowRight.trigger('click');
        await arrowRight.trigger('click');
        await arrowRight.trigger('click');
        await arrowRight.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        // Check if it doesnt exceed its range to the right
        await arrowRight.trigger('click');
        await arrowRight.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );
    });

    it('should navigate using the buttons', async () => {
        const wrapper = await createWrapper();
        const data = wrapper.vm._data;
        const itemPerPage = wrapper.vm.itemPerPage;
        const imageLength = wrapper.vm.images.length;

        const buttons = wrapper.findAll('.sw-image-slider__buttons-element');
        const containerScrollable = wrapper.find('.sw-image-slider__image-scrollable');

        const staticStyles = 'width: 500%; gap: 20px;';

        expect(buttons).toHaveLength(5);

        let expectedIndex = 0;
        let translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(data.currentPageNumber).toBe(0);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        expectedIndex = 3;
        await buttons.at(expectedIndex).trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        expectedIndex = 1;
        await buttons.at(expectedIndex).trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );
    });

    it('should navigate by arrows correctly when there are more than 1 item per page', async () => {
        const wrapper = await createWrapper({
            itemPerPage: 2,
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
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        // First ArrowRight click
        await arrowRight.trigger('click');
        expectedIndex = 1;
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        // First ArrowLeft click
        await arrowLeft.trigger('click');
        expectedIndex = 0;
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        // Check if it doesnt exceed its range to the left
        await arrowLeft.trigger('click');
        await arrowLeft.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        // Click a bit further to the right
        expectedIndex = 2;
        await arrowRight.trigger('click');
        await arrowRight.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        // Check if it doesnt exceed its range to the right
        await arrowRight.trigger('click');
        await arrowRight.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );
    });

    it('should navigate by buttons correctly when there are more than 1 item per page', async () => {
        const wrapper = await createWrapper({
            itemPerPage: 2,
        });

        const data = wrapper.vm._data;
        const itemPerPage = wrapper.vm.itemPerPage;
        const imageLength = wrapper.vm.images.length;

        const buttons = wrapper.findAll('.sw-image-slider__buttons-element');
        const containerScrollable = wrapper.find('.sw-image-slider__image-scrollable');

        const staticStyles = 'width: 250%; gap: 20px;';

        expect(buttons).toHaveLength(3);

        // Move to 1st page which contain 1st and 2nd images
        let expectedIndex = 0;
        let translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(data.currentPageNumber).toBe(0);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        // Move to last page which contain 4th and 5th images
        expectedIndex = 2;
        await buttons.at(expectedIndex).trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );

        // Move to 2nd page which contain 3rd and 4th images
        expectedIndex = 1;
        await buttons.at(expectedIndex).trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        translateAmount = getTranslateAmount(imageLength, itemPerPage, expectedIndex);
        expect(containerScrollable.attributes().style).toContain(
            `${staticStyles} transform: translateX(-${translateAmount}%);`,
        );
    });

    it('should mark aria-hidden correctly when navigating', async () => {
        const wrapper = await createWrapper({
            itemPerPage: 2,
        });

        const buttons = wrapper.findAll('.sw-image-slider__buttons-element');
        expect(buttons).toHaveLength(3);

        // Move to 1st page, mark 1st and 2nd images not hidden
        let expectedIndex;
        const imageWrappers = wrapper.findAll('.sw-image-slider__element-wrapper');
        imageWrappers.forEach((item, index) => {
            expect(item.attributes()['aria-hidden']).toBe(index === 0 || index === 1 ? undefined : 'true');
        });

        // Move to last page, mark 4th and 5th images not hidden
        expectedIndex = 2;
        await buttons.at(expectedIndex).trigger('click');

        imageWrappers.forEach((item, index) => {
            expect(item.attributes()['aria-hidden']).toBe(index === 3 || index === 4 ? undefined : 'true');
        });

        // Move to 2nd page, mark 3rd and 4th images not hidden
        expectedIndex = 1;
        await buttons.at(expectedIndex).trigger('click');

        imageWrappers.forEach((item, index) => {
            expect(item.attributes()['aria-hidden']).toBe(index === 2 || index === 3 ? undefined : 'true');
        });
    });

    it('should show active border around item after clicking on it', async () => {
        const wrapper = await createWrapper({
            itemPerPage: 5,
        });

        let expectedIndex = 0;
        const imageContainers = wrapper.findAll('.sw-image-slider__element-container');

        imageContainers.forEach((item, index) => {
            expect(item.classes('is--active')).toBe(index === expectedIndex);
        });

        expectedIndex = 1;
        await imageContainers.at(expectedIndex).trigger('click');

        imageContainers.forEach((item, index) => {
            expect(item.classes('is--active')).toBe(index === expectedIndex);
        });

        expectedIndex = 3;
        await imageContainers.at(expectedIndex).trigger('click');

        imageContainers.forEach((item, index) => {
            expect(item.classes('is--active')).toBe(index === expectedIndex);
        });
    });

    it('should navigate back to first page by next arrow or last page by prev arrow when rewind is active', async () => {
        const wrapper = await createWrapper({
            itemPerPage: 2,
            rewind: true,
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
        await arrowRight.trigger('click');
        await arrowRight.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);

        // Check if it go to the last page
        expectedIndex = 2;
        await arrowLeft.trigger('click');
        expect(data.currentPageNumber).toBe(expectedIndex);
    });
});
