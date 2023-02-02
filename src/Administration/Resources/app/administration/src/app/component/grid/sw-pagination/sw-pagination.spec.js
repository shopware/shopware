/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/grid/sw-pagination';

describe('src/component/grid/sw-pagination', () => {
    let wrapper;

    function getActivePage() {
        return wrapper.find('.sw-pagination__list-button.is-active');
    }

    function getButtonAtPosition(position) {
        const allPageButtons = wrapper.findAll('button.sw-pagination__list-button').wrappers;

        return allPageButtons[position];
    }

    function getPositionOfActiveButton() {
        const allPageButtons = wrapper.findAll(
            '.sw-pagination__list-item :not(span.sw-pagination__list-separator)'
        ).wrappers;

        const positionOfActivePageButton = allPageButtons.findIndex(currentElement => {
            const buttonOfCurrentElement = currentElement.find('button');

            return buttonOfCurrentElement.attributes('class').includes('is-active');
        });

        return positionOfActivePageButton;
    }

    async function checkNextPage(currentPage, direction, arrowButton) {
        expect(getActivePage().text()).toBe(currentPage.toString());

        if (currentPage >= wrapper.vm.maxPage) {
            return;
        }

        if (arrowButton === undefined) {
            const nextPageButton = getButtonAtPosition(getPositionOfActiveButton() + 1);

            // visit next page
            await nextPageButton.trigger('click');
            await wrapper.vm.$nextTick();
        } else {
            // visit next page
            await arrowButton.trigger('click');
            await wrapper.vm.$nextTick();
        }

        if (direction === 'right') {
            currentPage += 1;
        } else {
            currentPage -= 1;
        }

        await checkNextPage(currentPage, direction, arrowButton);
    }

    async function createWrapper() {
        return shallowMount(await Shopware.Component.build('sw-pagination'), {
            propsData: {
                total: 275,
                limit: 25,
                page: 1,
                autoHide: false
            },
            stubs: {
                'sw-icon': {
                    template: '<div class="icon"></div>'
                },
                'sw-field': {
                    template: '<div class="field"></div>'
                }
            },
            attachTo: document.body,
        });
    }

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have two arrow icons', async () => {
        const [leftArrow, rightArrow] = wrapper.findAll('div.icon').wrappers;

        expect(leftArrow.exists()).toBe(true);
        expect(leftArrow.attributes('name')).toBe('regular-chevron-left-xs');

        expect(rightArrow.exists()).toBe(true);
        expect(rightArrow.attributes('name')).toBe('regular-chevron-right-xs');
    });

    it('should have the right amount of elements', async () => {
        const pageButtons = wrapper.findAll('button.sw-pagination__list-button');
        expect(pageButtons.length).toBe(6);

        const separator = wrapper.findAll('.sw-pagination__list-separator');
        expect(separator.length).toBe(1);

        const activeButton = wrapper.findAll('.sw-pagination__list-button.is-active');
        expect(activeButton.length).toBe(1);
    });

    it('should have right amount of elements when on third page', async () => {
        await wrapper.vm.changePageByPageNumber(3);

        const allPageButtons = wrapper.findAll('.sw-pagination__list-button');
        expect(allPageButtons.length).toBe(7);

        const separator = wrapper.findAll('.sw-pagination__list-separator');
        expect(separator.length).toBe(1);

        const activePageButton = wrapper.find('.sw-pagination__list-button.is-active');
        expect(activePageButton.exists()).toBe(true);
    });

    it('should have right amount of elements when on ninth page', async () => {
        await wrapper.vm.changePageByPageNumber(9);

        const allPageButtons = wrapper.findAll('.sw-pagination__list-button');
        expect(allPageButtons.length).toBe(7);

        const separator = wrapper.findAll('.sw-pagination__list-separator');
        expect(separator.length).toBe(1);

        const activePageButton = wrapper.find('.sw-pagination__list-button.is-active');
        expect(activePageButton.exists()).toBe(true);
    });

    it('should navigate to another page via arrows', async () => {
        const [leftArrow, rightArrow] = wrapper.findAll('div.icon').wrappers;

        expect(getActivePage().text()).toBe('1');

        await rightArrow.trigger('click');

        expect(getActivePage().text()).toBe('2');

        await leftArrow.trigger('click');

        expect(getActivePage().text()).toBe('1');
    });

    it('should emit event when clicking on an arrow', async () => {
        const rightArrow = wrapper.find('div.icon[name="regular-chevron-right-xs"]');

        await rightArrow.trigger('click');

        const pageChangeEvents = wrapper.emitted()['page-change'];
        expect(pageChangeEvents.length).toBe(1);

        const [eventObject] = pageChangeEvents[0];
        expect(eventObject).toEqual({ limit: 25, page: 2 });
    });

    it('should navigate to another page via page button', async () => {
        expect(getActivePage().text()).toBe('1');

        const secondPageButton = wrapper.find('button.sw-pagination__list-button:not(.is-active)');

        await secondPageButton.trigger('click');

        expect(getActivePage().text()).toBe('2');
    });

    it('should emit event when clicking on a page button', async () => {
        const secondPageButton = wrapper.find('button.sw-pagination__list-button:not(.is-active)');

        await secondPageButton.trigger('click');

        const pageChangeEvents = wrapper.emitted()['page-change'];
        expect(pageChangeEvents.length).toBe(1);

        const [eventObject] = pageChangeEvents[0];
        expect(eventObject).toEqual({ limit: 25, page: 2 });
    });

    it('should navigate to fourth page via a page button', async () => {
        // set starting point to three
        await wrapper.vm.changePageByPageNumber(3);

        expect(getActivePage().text()).toBe('3');

        const fourthPageButton = getButtonAtPosition(3);
        expect(fourthPageButton.text()).toBe('4');
    });

    it('should navigate to eight page via a page button', async () => {
        // set starting point to nine
        await wrapper.vm.changePageByPageNumber(9);

        expect(getActivePage().text()).toBe('9');

        const fourthPageButton = getButtonAtPosition(3);
        expect(fourthPageButton.text()).toBe('8');
    });

    it('should navigate through complete pagination only with page button', async () => {
        const startingPoint = wrapper.vm.currentPage;

        await checkNextPage(startingPoint, 'right');
    });

    it('should navigate through complete pagination only with arrows', async () => {
        const startingPoint = wrapper.vm.currentPage;
        const [leftArrow, rightArrow] = wrapper.findAll('div.icon').wrappers;

        await checkNextPage(startingPoint, 'right', rightArrow);
        await checkNextPage(11, 'left', leftArrow);
    });

    it('should jump to first page', async () => {
        wrapper.vm.changePageByPageNumber(2);

        expect(wrapper.vm.currentPage).toBe(2);

        // go to first page
        wrapper.vm.firstPage();

        expect(wrapper.vm.currentPage).toBe(1);
    });

    it('should jump to last page', async () => {
        expect(wrapper.vm.currentPage).toBe(1);

        // go to last page
        wrapper.vm.lastPage();

        const lastPage = wrapper.vm.maxPage;
        expect(wrapper.vm.currentPage).toBe(lastPage);
    });

    it('should jump to correct page by number', async () => {
        expect(wrapper.vm.currentPage).toBe(1);

        // jump to eight page
        wrapper.vm.changePageByPageNumber(8);

        expect(wrapper.vm.currentPage).toBe(8);
    });

    it('should return correct last page number', async () => {
        expect(wrapper.vm.maxPage).toBe(11);
    });

    it('should return correct range', async () => {
        const range = wrapper.vm.range(1, 3);

        expect(range).toEqual([1, 2, 3]);
    });

    it('should be visible when autoHide is set to false', async () => {
        expect(wrapper.props('autoHide')).toBe(false);
        expect(wrapper.exists()).toBe(true);
    });

    it('should have right amount of elements when setting the prop totalVisible to 3', async () => {
        await wrapper.setProps({
            totalVisible: 3
        });

        await wrapper.vm.changePageByPageNumber(2);

        expect(wrapper.findAll('.sw-pagination__list-button').length).toBe(3);
        expect(wrapper.findAll('.sw-pagination__list-separator').length).toBe(1);

        expect(wrapper.find('.sw-pagination__list-button.is-active').exists()).toBe(true);

        const rightArrow = wrapper.find('div.icon[name="regular-chevron-right-xs"]');
        await rightArrow.trigger('click');

        expect(wrapper.findAll('.sw-pagination__list-button').length).toBe(3);
        expect(wrapper.findAll('.sw-pagination__list-separator').length).toBe(2);
    });
});
