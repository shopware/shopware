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

    function checkNextPage(currentPage, direction, arrowButton) {
        expect(getActivePage().text()).toBe(currentPage.toString());

        if (currentPage >= wrapper.vm.maxPage) {
            return;
        }

        if (arrowButton === undefined) {
            const nextPageButton = getButtonAtPosition(getPositionOfActiveButton() + 1);

            // visit next page
            nextPageButton.trigger('click');
        } else {
            // visit next page
            arrowButton.trigger('click');
        }

        if (direction === 'right') {
            currentPage += 1;
        } else {
            currentPage -= 1;
        }

        checkNextPage(currentPage, direction, arrowButton);
    }

    function createWrapper() {
        return shallowMount(Shopware.Component.build('sw-pagination'), {
            propsData: {
                total: 275,
                limit: 25,
                page: 1,
                autoHide: false
            },
            mocks: {
                $tc: key => key
            },
            stubs: {
                'sw-icon': '<div class="icon"></div>',
                'sw-field': '<div class="field"></div>'
            }
        });
    }

    beforeEach(() => {
        wrapper = createWrapper();
    });

    it('should be a Vue.JS component', () => {
        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should have two arrow icons', () => {
        const [leftArrow, rightArrow] = wrapper.findAll('div.icon').wrappers;

        expect(leftArrow.exists()).toBe(true);
        expect(leftArrow.attributes('name')).toBe('small-arrow-medium-left');

        expect(rightArrow.exists()).toBe(true);
        expect(rightArrow.attributes('name')).toBe('small-arrow-medium-right');
    });

    it('should have the right amount of elements', () => {
        const pageButtons = wrapper.findAll('button.sw-pagination__list-button');
        expect(pageButtons.length).toBe(6);

        const seperator = wrapper.findAll('.sw-pagination__list-separator');
        expect(seperator.length).toBe(1);

        const activeButton = wrapper.findAll('.sw-pagination__list-button.is-active');
        expect(activeButton.length).toBe(1);
    });

    it('should have right amount of elements when on fourth page', () => {
        wrapper.vm.changePageByPageNumber(3);

        const allPageButtons = wrapper.findAll('.sw-pagination__list-button');
        expect(allPageButtons.length).toBe(7);

        const seperator = wrapper.findAll('.sw-pagination__list-separator');
        expect(seperator.length).toBe(1);

        const activePageButton = wrapper.find('.sw-pagination__list-button.is-active');
        expect(activePageButton.exists()).toBe(true);
    });

    it('should have right amount of elements when on ninth page', () => {
        wrapper.vm.changePageByPageNumber(9);

        const allPageButtons = wrapper.findAll('.sw-pagination__list-button');
        expect(allPageButtons.length).toBe(7);

        const seperator = wrapper.findAll('.sw-pagination__list-separator');
        expect(seperator.length).toBe(1);

        const activePageButton = wrapper.find('.sw-pagination__list-button.is-active');
        expect(activePageButton.exists()).toBe(true);
    });

    it('should navigate to another page via arrows', () => {
        const [leftArrow, rightArrow] = wrapper.findAll('div.icon').wrappers;

        expect(getActivePage().text()).toBe('1');

        rightArrow.trigger('click');

        expect(getActivePage().text()).toBe('2');

        leftArrow.trigger('click');

        expect(getActivePage().text()).toBe('1');
    });

    it('should emit event when clicking on an arrow', () => {
        const rightArrow = wrapper.find('div.icon[name="small-arrow-medium-right"]');

        rightArrow.trigger('click');

        const pageChangeEvents = wrapper.emitted()['page-change'];
        expect(pageChangeEvents.length).toBe(1);

        const [eventObject] = pageChangeEvents[0];
        expect(eventObject).toEqual({ limit: 25, page: 2 });
    });

    it('should navigate to another page via page button', () => {
        expect(getActivePage().text()).toBe('1');

        const secondPageButton = wrapper.find('button.sw-pagination__list-button:not(.is-active)');

        secondPageButton.trigger('click');

        expect(getActivePage().text()).toBe('2');
    });

    it('should emit event when clicking on a page button', () => {
        const secondPageButton = wrapper.find('button.sw-pagination__list-button:not(.is-active)');

        secondPageButton.trigger('click');

        const pageChangeEvents = wrapper.emitted()['page-change'];
        expect(pageChangeEvents.length).toBe(1);

        const [eventObject] = pageChangeEvents[0];
        expect(eventObject).toEqual({ limit: 25, page: 2 });
    });

    it('should navigate to fourth page via a page button', () => {
        // set starting point to three
        wrapper.vm.changePageByPageNumber(3);

        expect(getActivePage().text()).toBe('3');

        const fourthPageButton = getButtonAtPosition(3);
        expect(fourthPageButton.text()).toBe('4');
    });

    it('should navigate to eight page via a page button', () => {
        // set starting point to nine
        wrapper.vm.changePageByPageNumber(9);

        expect(getActivePage().text()).toBe('9');

        const fourthPageButton = getButtonAtPosition(3);
        expect(fourthPageButton.text()).toBe('8');
    });

    it('should navigate through complete pagination only with page button', () => {
        const startingPoint = wrapper.vm.currentPage;

        checkNextPage(startingPoint, 'right');
    });

    it('should navigate through complete pagination only with arrows', () => {
        const startingPoint = wrapper.vm.currentPage;
        const [leftArrow, rightArrow] = wrapper.findAll('div.icon').wrappers;

        checkNextPage(startingPoint, 'right', rightArrow);
        checkNextPage(11, 'left', leftArrow);
    });

    it('should jump to first page', () => {
        wrapper.vm.changePageByPageNumber(2);

        expect(wrapper.vm.currentPage).toBe(2);

        // go to first page
        wrapper.vm.firstPage();

        expect(wrapper.vm.currentPage).toBe(1);
    });

    it('should jump to last page', () => {
        expect(wrapper.vm.currentPage).toBe(1);

        // go to last page
        wrapper.vm.lastPage();

        const lastPage = wrapper.vm.maxPage;
        expect(wrapper.vm.currentPage).toBe(lastPage);
    });

    it('should jump to correct page by number', () => {
        expect(wrapper.vm.currentPage).toBe(1);

        // jump to eight page
        wrapper.vm.changePageByPageNumber(8);

        expect(wrapper.vm.currentPage).toBe(8);
    });

    it('should return correct last page number', () => {
        expect(wrapper.vm.maxPage).toBe(11);
    });

    it('should return correct range', () => {
        const range = wrapper.vm.range(1, 3);

        expect(range).toEqual([1, 2, 3]);
    });

    it('should be visisble when autoHide is set to false', () => {
        expect(wrapper.props('autoHide')).toBe(false);
        expect(wrapper.exists()).toBe(true);
    });
});
