/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import { deepMergeObject } from 'src/core/service/utils/object.utils';
import 'src/app/component/list/sw-sortable-list';

const listItems = [
    { id: 0 },
    { id: 1 },
    { id: 2 },
];

async function createWrapper(userConfig = {}) {
    const defaultConfig = {
        props: {
            items: [...listItems],
        },
        slots: {
            item: (propsData) => {
                return propsData.item.id;
            },
        },
        global: {
            stubs: {
                'sw-empty-state': true,
            },
            directives: {
                draggable: {},
                droppable: {},
                tooltip: {},
            },
            provide: {
                repositoryFactory: {
                    create: () => ({ save: () => Promise.resolve() }),
                },
            },
            mocks: {
                $tc: (v) => v,
            },
            sync: true,
        },
    };

    const wrapper = shallowMount(
        await Shopware.Component.build('sw-sortable-list'),
        deepMergeObject(defaultConfig, userConfig),
    );

    await flushPromises();

    return wrapper;
}

describe('src/component/list/sw-sortable-list', () => {
    /** @type Wrapper */
    let wrapper;

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

        await flushPromises();

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

        await flushPromises();

        wrapper.vm.onDrop();

        expect(wrapper.emitted()['items-sorted'][0][0]).toEqual(expect.arrayContaining(expectedListItems));
    });

    it('should not sort if disabled', async () => {
        wrapper = await createWrapper({
            propsData: {
                sortable: false,
            },
        });

        wrapper.vm.onDragEnter(listItems[0], listItems[2]);

        await flushPromises();

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
        await flushPromises();

        let items = wrapper.findAll('.sw-sortable-list__item');
        expect(items).toHaveLength(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');

        wrapper.vm.onDragEnter(null, listItems[0]);
        await flushPromises();

        items = wrapper.findAll('.sw-sortable-list__item');
        expect(items).toHaveLength(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');
    });

    it('should not sort items with same id', async () => {
        const brokenItems = [
            { id: 1 },
            { id: 1 },
            { id: 1 },
        ];

        wrapper = await createWrapper({
            items: brokenItems,
        });

        wrapper.vm.onDragEnter(brokenItems[0], brokenItems[1]);
        wrapper.vm.onDragEnter(brokenItems[0], brokenItems[2]);
        wrapper.vm.onDragEnter(brokenItems[1], brokenItems[2]);

        await flushPromises();

        const items = wrapper.findAll('.sw-sortable-list__item');
        expect(items).toHaveLength(3);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('1');
        expect(items.at(2).text()).toBe('2');
    });

    it('should not sort items without id', async () => {
        const brokenItems = [
            { id: null },
            { id: undefined },
            { id: '' },
        ];

        wrapper = await createWrapper({
            items: brokenItems,
        });

        wrapper.vm.onDragEnter(brokenItems[0], brokenItems[1]);
        wrapper.vm.onDragEnter(brokenItems[0], brokenItems[2]);
        wrapper.vm.onDragEnter(brokenItems[1], brokenItems[2]);

        await flushPromises();

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

        await flushPromises();

        // reset the previous drag-drop => order should be reset
        wrapper.vm.onDragEnter(listItems[1], listItems[0]);
        wrapper.vm.onDrop();

        await flushPromises();

        expect(wrapper.emitted()['items-sorted']).toHaveLength(2);

        expect(wrapper.emitted()['items-sorted'][0][1]).toBeFalsy();
        expect(wrapper.emitted()['items-sorted'][1][1]).toBeTruthy();
    });

    it('should set dragElement', async () => {
        wrapper = await createWrapper();

        const dragElement = {
            id: 'drag-element-id',
        };

        wrapper.vm.onDragStart({}, {}, dragElement);

        await flushPromises();

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

        await flushPromises();

        expect(scrollByOptions).toHaveLength(0);
    });
});
