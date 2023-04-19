/**
 * @package admin
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import { deepMergeObject } from 'src/core/service/utils/object.utils';
import 'src/app/component/list/sw-sortable-list';

const listItems = [{ id: 0 }, { id: 1 }, { id: 2 }];

async function createWrapper(userConfig = {}) {
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
            },
        },
        provide: {
            repositoryFactory: {
                create: () => ({ save: () => Promise.resolve() }),
            },
        },
        mocks: {
            $tc: v => v,
        },
        sync: true,
    };

    return shallowMount(await Shopware.Component.build('sw-sortable-list'), deepMergeObject(defaultConfig, userConfig));
}

describe('src/component/list/sw-sortable-list', () => {
    /** @type Wrapper */
    let wrapper;

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have a list of items', async () => {
        wrapper = await createWrapper();
        const list = wrapper.find('.sw-sortable-list');

        expect(list.exists()).toBeTruthy();

        const items = list.findAll('.sw-sortable-list__item');

        expect(items).toHaveLength(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');
    });

    it('should sort the list on dragging', async () => {
        wrapper = await createWrapper();
        wrapper.vm.onDragEnter(listItems[0], listItems[2]);

        await wrapper.vm.$nextTick();

        const items = wrapper.findAll('.sw-sortable-list__item');

        expect(items).toHaveLength(3);
        expect(items.at(0).text()).toBe('1');
        expect(items.at(1).text()).toBe('2');
        expect(items.at(2).text()).toBe('0');
    });

    it('should return the sorted list after drop', async () => {
        wrapper = await createWrapper();
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
        wrapper = await createWrapper({
            propsData: {
                sortable: false,
            },
        });


        wrapper.vm.onDragEnter(listItems[0], listItems[2]);

        await wrapper.vm.$nextTick();

        const items = wrapper.findAll('.sw-sortable-list__item');

        expect(items).toHaveLength(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');
    });

    it('should not emit drop event if disabled', async () => {
        wrapper = await createWrapper({
            propsData: {
                sortable: false,
            },
        });

        wrapper.vm.onDrop();

        expect(wrapper.emitted().onDrop).toBeFalsy();
    });

    it('should not sort if no items are provided', async () => {
        wrapper = await createWrapper();

        wrapper.vm.onDragEnter(listItems[0]);
        await wrapper.vm.$nextTick();

        let items = wrapper.findAll('.sw-sortable-list__item');
        expect(items).toHaveLength(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');

        wrapper.vm.onDragEnter(null, listItems[0]);
        await wrapper.vm.$nextTick();

        items = wrapper.findAll('.sw-sortable-list__item');
        expect(items).toHaveLength(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');
    });

    it('should not sort items with same id', async () => {
        const brokenItems = [{ id: 1 }, { id: 1 }, { id: 1 }];

        wrapper = await createWrapper({
            items: brokenItems,
        });

        wrapper.vm.onDragEnter(brokenItems[0], brokenItems[1]);
        wrapper.vm.onDragEnter(brokenItems[0], brokenItems[2]);
        wrapper.vm.onDragEnter(brokenItems[1], brokenItems[2]);

        await wrapper.vm.$nextTick();

        const items = wrapper.findAll('.sw-sortable-list__item');
        expect(items).toHaveLength(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');
    });

    it('should not sort items without id', async () => {
        const brokenItems = [{ id: null }, { id: undefined }, { id: '' }];

        wrapper = await createWrapper({
            items: brokenItems,
        });

        wrapper.vm.onDragEnter(brokenItems[0], brokenItems[1]);
        wrapper.vm.onDragEnter(brokenItems[0], brokenItems[2]);
        wrapper.vm.onDragEnter(brokenItems[1], brokenItems[2]);

        await wrapper.vm.$nextTick();

        const items = wrapper.findAll('.sw-sortable-list__item');
        expect(items).toHaveLength(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');
    });

    it('should emit whether order has changed or not', async () => {
        wrapper = await createWrapper();

        wrapper.vm.onDragEnter(listItems[0], listItems[1]);
        wrapper.vm.onDrop();

        await wrapper.vm.$nextTick();

        // reset the previous drag-drop => order should be reset
        wrapper.vm.onDragEnter(listItems[1], listItems[0]);
        wrapper.vm.onDrop();

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted().itemsSorted).toHaveLength(2);

        expect(wrapper.emitted().itemsSorted[0][1]).toBeFalsy();
        expect(wrapper.emitted().itemsSorted[1][1]).toBeTruthy();
    });

    it('should set dragElement', async () => {
        wrapper = await createWrapper();

        const dragElement = {
            id: 'drag-element-id',
        };

        wrapper.vm.onDragStart({}, {}, dragElement);

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.dragElement).toEqual(dragElement);
    });

    it('should add event to scrollable parent', async () => {
        wrapper = await createWrapper({
            propsData: {
                scrollOnDrag: true,
            },
        });

        wrapper.vm.$el = {
            scrollHeight: 10,
            clientHeight: 0,
            id: 'element',
            addEventListener(type, callback) {
                expect(type).toBe('scroll');
                expect(callback).toEqual(wrapper.vm.onScroll);
            },
        };

        wrapper.vm.onDragStart({}, {}, { id: 'drag-element-id' });
    });

    it('should find scrollable parent', async () => {
        wrapper = await createWrapper();

        const scrollableParent = {
            scrollHeight: 10,
            clientHeight: 0,
            id: 'scrollable-parent',
        };

        wrapper.vm.$el = {
            scrollHeight: 0,
            clientHeight: 0,
            id: 'element',
            parentElement: {
                scrollHeight: 1,
                clientHeight: 5,
                id: 'parent-element',
                parentElement: scrollableParent,
            },
        };

        expect(wrapper.vm.scrollableParent).toEqual(scrollableParent);
    });

    it('should scroll when in scroll margin', async () => {
        wrapper = await createWrapper({
            propsData: {
                scrollOnDrag: true,
                scrollOnDragConf: {
                    speed: 10,
                    margin: 10,
                    accelerationMargin: 0,
                },
            },
        });

        wrapper.vm.dragElement = {
            id: 'drag-element-id',
            getBoundingClientRect() {
                return {
                    top: 100,
                    bottom: 91,
                };
            },
        };

        const scrollByOptions = [];
        wrapper.vm.$el = {
            scrollHeight: 10,
            clientHeight: 0,
            id: 'element',
            getBoundingClientRect() {
                return {
                    top: 91,
                    bottom: 100,
                };
            },
            scrollBy(options) {
                scrollByOptions.push(options);
            },
        };

        wrapper.vm.scroll();

        await wrapper.vm.$nextTick();

        expect(scrollByOptions).toHaveLength(2);
        expect(scrollByOptions[0].top).toBe(-10);
        expect(scrollByOptions[1].top).toBe(10);
    });

    it('should scroll accelerated when in acceleration margin', async () => {
        wrapper = await createWrapper({
            propsData: {
                scrollOnDrag: true,
                scrollOnDragConf: {
                    speed: 10,
                    margin: 10,
                    accelerationMargin: 0,
                },
            },
        });

        wrapper.vm.dragElement = {
            id: 'drag-element-id',
            getBoundingClientRect() {
                return {
                    top: 100,
                    bottom: 110,
                };
            },
        };

        const scrollByOptions = [];
        wrapper.vm.$el = {
            scrollHeight: 10,
            clientHeight: 0,
            id: 'element',
            getBoundingClientRect() {
                return {
                    top: 110,
                    bottom: 100,
                };
            },
            scrollBy(options) {
                scrollByOptions.push(options);
            },
        };

        wrapper.vm.scroll();

        await wrapper.vm.$nextTick();

        expect(scrollByOptions).toHaveLength(2);
        expect(scrollByOptions[0].top).toBeLessThan(-10);
        expect(scrollByOptions[1].top).toBeGreaterThan(10);
    });

    it('should not scroll when not in scroll margin', async () => {
        wrapper = await createWrapper({
            propsData: {
                scrollOnDrag: true,
                scrollOnDragConf: {
                    speed: 10,
                    margin: 10,
                    accelerationMargin: 0,
                },
            },
        });

        wrapper.vm.dragElement = {
            id: 'drag-element-id',
            getBoundingClientRect() {
                return {
                    top: 100,
                    bottom: 0,
                };
            },
        };

        const scrollByOptions = [];
        wrapper.vm.$el = {
            scrollHeight: 10,
            clientHeight: 0,
            id: 'element',
            getBoundingClientRect() {
                return {
                    top: 0,
                    bottom: 100,
                };
            },
            scrollBy(options) {
                scrollByOptions.push(options);
            },
        };

        wrapper.vm.scroll();

        await wrapper.vm.$nextTick();

        expect(scrollByOptions).toHaveLength(0);
    });
});
