import { createLocalVue, shallowMount } from '@vue/test-utils';
import { deepMergeObject } from '../../../../src/core/service/utils/object.utils';
import 'src/app/component/list/sw-sortable-list';

const listItems = [{ id: 0 }, { id: 1 }, { id: 2 }];

function createWrapper(userConfig = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.directive('droppable', {});
    localVue.directive('draggable', {});

    const defaultConfig = {
        localVue,
        propsData: {
            items: [...listItems],
        },
        scopedSlots: {
            item: (propsData) => {
                return propsData.item.id;
            }
        },
        provide: {
            repositoryFactory: {
                create: () => ({ save: () => Promise.resolve() })
            },
        },
        mocks: {
            $tc: v => v,
        },
        sync: true,
    };

    return shallowMount(Shopware.Component.build('sw-sortable-list'), deepMergeObject(defaultConfig, userConfig));
}

describe('src/component/list/sw-sortable-list', () => {
    /** @type Wrapper */
    let wrapper;

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have a list of items', async () => {
        wrapper = createWrapper();
        const list = wrapper.find('.sw-sortable-list');

        expect(list.exists()).toBeTruthy();

        const items = list.findAll('.sw-sortable-list__item');

        expect(items.length).toBe(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');
    });

    it('should sort the list on dragging', async () => {
        wrapper = createWrapper();
        wrapper.vm.onDragEnter(listItems[0], listItems[2]);

        await wrapper.vm.$nextTick();

        const items = wrapper.findAll('.sw-sortable-list__item');

        expect(items.length).toBe(3);
        expect(items.at(0).text()).toBe('1');
        expect(items.at(1).text()).toBe('2');
        expect(items.at(2).text()).toBe('0');
    });

    it('should return the sorted list after drop', async () => {
        wrapper = createWrapper();
        const expectedListItems = [
            listItems[1],
            listItems[2],
            listItems[0],
        ];

        wrapper.vm.onDragEnter(listItems[0], listItems[2]);

        await wrapper.vm.$nextTick();

        wrapper.vm.onDrop();

        expect(wrapper.emitted().itemsSorted[0][0]).toEqual(expect.arrayContaining(expectedListItems));
    });

    it('should not sort if disabled', async () => {
        wrapper = createWrapper({
            propsData: {
                sortable: false,
            },
        });


        wrapper.vm.onDragEnter(listItems[0], listItems[2]);

        await wrapper.vm.$nextTick();

        const items = wrapper.findAll('.sw-sortable-list__item');

        expect(items.length).toBe(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');
    });

    it('should not emit drop event if disabled', async () => {
        wrapper = createWrapper({
            propsData: {
                sortable: false,
            },
        });

        wrapper.vm.onDrop();

        expect(wrapper.emitted().onDrop).toBeFalsy();
    });

    it('should not sort if no items are provided', async () => {
        wrapper = createWrapper();

        wrapper.vm.onDragEnter(listItems[0]);
        await wrapper.vm.$nextTick();

        let items = wrapper.findAll('.sw-sortable-list__item');
        expect(items.length).toBe(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');

        wrapper.vm.onDragEnter(null, listItems[0]);
        await wrapper.vm.$nextTick();

        items = wrapper.findAll('.sw-sortable-list__item');
        expect(items.length).toBe(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');
    });

    it('should not sort items with same id', async () => {
        const brokenItems = [{ id: 1 }, { id: 1 }, { id: 1 }];

        wrapper = createWrapper({
            items: brokenItems,
        });

        wrapper.vm.onDragEnter(brokenItems[0], brokenItems[1]);
        wrapper.vm.onDragEnter(brokenItems[0], brokenItems[2]);
        wrapper.vm.onDragEnter(brokenItems[1], brokenItems[2]);

        await wrapper.vm.$nextTick();

        const items = wrapper.findAll('.sw-sortable-list__item');
        expect(items.length).toBe(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');
    });

    it('should not sort items without id', async () => {
        const brokenItems = [{ id: null }, { id: undefined }, { id: '' }];

        wrapper = createWrapper({
            items: brokenItems,
        });

        wrapper.vm.onDragEnter(brokenItems[0], brokenItems[1]);
        wrapper.vm.onDragEnter(brokenItems[0], brokenItems[2]);
        wrapper.vm.onDragEnter(brokenItems[1], brokenItems[2]);

        await wrapper.vm.$nextTick();

        const items = wrapper.findAll('.sw-sortable-list__item');
        expect(items.length).toBe(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');
    });

    it('should emit whether order has changed or not', async () => {
        wrapper = createWrapper();

        wrapper.vm.onDragEnter(listItems[0], listItems[1]);
        wrapper.vm.onDrop();

        await wrapper.vm.$nextTick();

        // reset the previous drag-drop => order should be reset
        wrapper.vm.onDragEnter(listItems[1], listItems[0]);
        wrapper.vm.onDrop();

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted().itemsSorted.length).toEqual(2);

        expect(wrapper.emitted().itemsSorted[0][1]).toBeFalsy();
        expect(wrapper.emitted().itemsSorted[1][1]).toBeTruthy();
    });
});
