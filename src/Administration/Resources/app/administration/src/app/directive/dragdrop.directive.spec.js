/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import { resetCurrentDrag, getCurrentDragElement } from 'src/app/directive/dragdrop.directive';

jest.useFakeTimers();
jest.spyOn(global, 'setTimeout');

const createWrapper = async (startingDragConfig) => {
    const div = document.createElement('div');
    div.id = 'root';
    document.body.appendChild(div);

    resetCurrentDrag();

    const dragdropComponent = {
        name: 'dragdrop-component',
        template: `
            <div>
                <span
                    v-for="i in 5"
                    :key="i"
                    :id="getIdName(i)"
                    v-droppable="{ data: { id: i }, dragGroup: 'sw-multi-snippet'}"
                    v-draggable="{ ...dragConf, data: { id: i } }"
                >
                    item {{ i }}
                </span>
            </div>
        `,
        computed: {
            dragConf() {
                return {
                    delay: 200,
                    dragGroup: 'sw-multi-snippet',
                    validDragCls: 'is--valid-drag',
                    onDragStart: this.onDragStart,
                    onDragEnter: this.onDragEnter,
                    onDrop: this.onDrop,
                    ...this.dragConfig,
                };
            },
        },
        data() {
            return {
                dragConfig: startingDragConfig,
            };
        },
        methods: {
            onDragStart(dragConfig, draggedElement, dragElement) {
                this.$emit('drag-start', {
                    dragConfig,
                    draggedElement,
                    dragElement,
                });
            },

            onDragEnter(dragData, dropData) {
                this.$emit('drag-enter', { dragData, dropData });
            },

            onDrop(dragData, dropData) {
                this.$emit('drop', { dragData, dropData });
            },

            getIdName(index) {
                return `sw-dragdrop--${index}`;
            },
        },
    };

    const wrapper = shallowMount(dragdropComponent, {
        attachTo: '#root',
    });

    await flushPromises();

    return wrapper;
};

describe('directives/dragdrop', () => {
    let wrapper;
    let draggable;
    let droppable;

    beforeAll(() => {
        draggable = Shopware.Directive.getByName('draggable');
        droppable = Shopware.Directive.getByName('droppable');
    });

    beforeEach(() => {
        document.body.innerHTML = '';
    });

    it('should be exist class name is--droppable', async () => {
        wrapper = await createWrapper();

        expect(wrapper.findAll('span').at(0).classes('is--droppable')).toBe(true);
    });

    it('should be exist class name is--draggable', async () => {
        wrapper = await createWrapper();

        expect(wrapper.findAll('span').at(0).classes('is--draggable')).toBe(true);
    });

    it('should remove class name `is--draggable` for the draggable directive', async () => {
        wrapper = await createWrapper();

        const mockElement = document.getElementById('sw-dragdrop--1');

        const mockBinding = {
            name: 'draggable',
            value: {
                data: {},
            },
        };

        expect(mockElement.className).toBe('is--droppable is--draggable');

        draggable.unmounted(mockElement, mockBinding);

        expect(mockElement.className).toBe('is--droppable');
    });

    it('should update data for the droppable directive with default config', async () => {
        wrapper = await createWrapper();

        const mockElement = document.getElementById('sw-dragdrop--2');

        const mockBinding = {
            name: 'droppable',
            value: {
                data: {},
                disabled: true,
            },
        };

        expect(mockElement.className).toBe('is--droppable is--draggable');

        draggable.updated(mockElement, mockBinding);

        expect(mockElement.className).toBe('is--droppable');
    });

    it('should update data for the droppable directive with new config', async () => {
        await createWrapper({
            disabled: true,
        });

        const mockElement = document.getElementById('sw-dragdrop--2');

        const mockBinding = {
            name: 'droppable',
            value: {
                data: {},
            },
        };

        expect(mockElement.className).toBe('is--droppable');

        draggable.updated(mockElement, mockBinding);

        expect(mockElement.className).toBe('is--droppable is--draggable');
    });

    it('should remove class name `is--droppable` for the droppable directive', async () => {
        wrapper = await createWrapper();

        const mockElement = document.getElementById('sw-dragdrop--3');

        const mockBinding = {
            name: 'droppable',
            value: {
                data: {},
            },
        };

        expect(mockElement.className).toBe('is--droppable is--draggable');

        droppable.unmounted(mockElement, mockBinding);

        expect(mockElement.className).toBe('is--draggable');
    });

    it('should create the correct class on drag', async () => {
        wrapper = await createWrapper({
            delay: 0,
        });

        const dragDrop1 = wrapper.find('#sw-dragdrop--1');

        expect(dragDrop1.classes()).not.toContain('is--dragging');

        await dragDrop1.trigger('mousedown', {
            buttons: 1,
        });

        expect(dragDrop1.classes()).toContain('is--dragging');
    });

    it('should set the correct values when dragDrop moves over dropzone', async () => {
        wrapper = await createWrapper({
            delay: 0,
        });

        const dragDrop1 = wrapper.find('#sw-dragdrop--1');
        const dragDrop2 = wrapper.find('#sw-dragdrop--2');

        dragDrop1.element.getBoundingClientRect = jest.fn(() => {
            return {
                left: 0,
                top: 0,
                width: 100,
                height: 100,
            };
        });
        dragDrop2.element.getBoundingClientRect = jest.fn(() => {
            return {
                left: 100,
                x: 100,
                top: 0,
                y: 0,
                width: 100,
                height: 100,
            };
        });

        await dragDrop1.trigger('mousedown', {
            buttons: 1,
        });

        await dragDrop2.trigger('touchmove', {
            touches: [
                {
                    pageX: 120,
                    pageY: 60,
                },
            ],
        });

        const currentDragElement = getCurrentDragElement();

        expect(currentDragElement.style.left).toBe('120px');
        expect(currentDragElement.style.top).toBe('60px');
        expect(currentDragElement.style.width).toBe('100px');
        expect(currentDragElement.classList.contains('is--drag-element')).toBe(true);
        expect(dragDrop2.classes('is--valid-drop')).toBe(true);
    });

    it('should set the correct values when dragDrop moves over an invalid dropzone', async () => {
        wrapper = await createWrapper({
            delay: 0,
            validateDrop: () => false,
        });

        const dragDrop1 = wrapper.find('#sw-dragdrop--1');
        const dragDrop2 = wrapper.find('#sw-dragdrop--2');

        dragDrop1.element.getBoundingClientRect = jest.fn(() => {
            return {
                left: 0,
                top: 0,
                width: 100,
                height: 100,
            };
        });
        dragDrop2.element.getBoundingClientRect = jest.fn(() => {
            return {
                left: 100,
                x: 100,
                top: 0,
                y: 0,
                width: 100,
                height: 100,
            };
        });

        await dragDrop1.trigger('mousedown', {
            buttons: 1,
        });

        await dragDrop2.trigger('touchmove', {
            touches: [
                {
                    pageX: 120,
                    pageY: 60,
                },
            ],
        });

        const currentDragElement = getCurrentDragElement();

        expect(currentDragElement.style.left).toBe('120px');
        expect(currentDragElement.style.top).toBe('60px');
        expect(currentDragElement.style.width).toBe('100px');
        expect(currentDragElement.classList.contains('is--drag-element')).toBe(true);
        expect(dragDrop2.classes('is--invalid-drop')).toBe(true);
    });

    it('should set the correct values when dragDrop leaves dropzone', async () => {
        wrapper = await createWrapper({
            delay: 0,
        });

        const dragDrop1 = wrapper.find('#sw-dragdrop--1');
        const dragDrop2 = wrapper.find('#sw-dragdrop--2');

        dragDrop2.element.getBoundingClientRect = jest.fn(() => {
            return {
                left: 100,
                x: 100,
                top: 0,
                y: 0,
                width: 100,
                height: 100,
            };
        });

        await dragDrop1.trigger('mousedown', {
            buttons: 1,
        });

        await dragDrop2.trigger('touchmove', {
            touches: [
                {
                    pageX: 120,
                    pageY: 60,
                },
            ],
        });

        expect(dragDrop2.classes('is--valid-drop')).toBe(true);

        await dragDrop2.trigger('touchmove', {
            touches: [
                {
                    pageX: 10,
                    pageY: 60,
                },
            ],
        });

        expect(dragDrop2.classes('is--valid-drop')).toBe(false);
    });

    it('should stop the drag correctly', async () => {
        wrapper = await createWrapper({
            delay: 0,
        });

        const dragDrop1 = wrapper.find('#sw-dragdrop--1');

        await dragDrop1.trigger('mousedown', {
            buttons: 1,
        });

        expect(dragDrop1.classes()).toContain('is--dragging');

        await dragDrop1.trigger('mouseup', {
            buttons: 1,
        });

        expect(dragDrop1.classes()).not.toContain('is--dragging');
    });

    it('should stop the drag correctly with delay', async () => {
        wrapper = await createWrapper({
            delay: 100,
        });

        const dragDrop1 = wrapper.find('#sw-dragdrop--1');

        await dragDrop1.trigger('mousedown', {
            buttons: 1,
        });

        jest.runAllTimers();

        expect(dragDrop1.classes()).toContain('is--dragging');

        await dragDrop1.trigger('mouseup', {
            buttons: 1,
        });

        expect(dragDrop1.classes()).not.toContain('is--dragging');
    });

    it('should stop the drag correctly with delay before it gets triggered', async () => {
        wrapper = await createWrapper({
            delay: 100,
        });

        const dragDrop1 = wrapper.find('#sw-dragdrop--1');

        await dragDrop1.trigger('mousedown', {
            buttons: 1,
        });

        // go 50ms forward, dragging should not be active
        jest.advanceTimersByTime(50);

        expect(dragDrop1.classes()).not.toContain('is--dragging');

        await dragDrop1.trigger('mouseup', {
            buttons: 1,
        });

        // if mouseup would not trigger then it would start dragging because it is over 100ms
        jest.advanceTimersByTime(60);

        expect(dragDrop1.classes()).not.toContain('is--dragging');
    });

    it('should execute the onDrop method when given', async () => {
        const mockMethod = jest.fn(() => null);
        wrapper = await createWrapper({
            delay: 0,
            onDrop: () => mockMethod(),
        });

        const dragDrop1 = wrapper.find('#sw-dragdrop--1');

        await dragDrop1.trigger('mousedown', {
            buttons: 1,
        });

        await dragDrop1.trigger('mouseup', {
            buttons: 1,
        });

        expect(mockMethod).toHaveBeenCalled();
    });

    it('should not do anything when event is no mouseEvent and no buttons were clicked', async () => {
        wrapper = await createWrapper({
            delay: 0,
        });

        const dragDrop1 = wrapper.find('#sw-dragdrop--1');

        await dragDrop1.trigger('mousedown', {
            buttons: 0,
        });

        expect(dragDrop1.classes()).not.toContain('is--dragging');
    });

    it('should update the dropConfig correctly so that the second drop is now invalid', async () => {
        wrapper = await createWrapper({
            delay: 0,
        });

        const dragDrop1 = wrapper.find('#sw-dragdrop--1');
        const dragDrop2 = wrapper.find('#sw-dragdrop--2');

        dragDrop1.element.getBoundingClientRect = jest.fn(() => {
            return {
                left: 0,
                top: 0,
                width: 100,
                height: 100,
            };
        });
        dragDrop2.element.getBoundingClientRect = jest.fn(() => {
            return {
                left: 100,
                x: 100,
                top: 0,
                y: 0,
                width: 100,
                height: 100,
            };
        });

        await dragDrop1.trigger('mousedown', {
            buttons: 1,
        });

        await dragDrop2.trigger('touchmove', {
            touches: [
                {
                    pageX: 120,
                    pageY: 60,
                },
            ],
        });

        let currentDragElement = getCurrentDragElement();

        expect(currentDragElement.style.left).toBe('120px');
        expect(currentDragElement.style.top).toBe('60px');
        expect(currentDragElement.style.width).toBe('100px');
        expect(currentDragElement.classList.contains('is--drag-element')).toBe(true);
        expect(dragDrop2.classes('is--invalid-drop')).toBe(false);

        await dragDrop1.trigger('mouseup', {
            buttons: 1,
        });

        // update dragConfig
        await wrapper.setData({
            dragConfig: {
                delay: 0,
                validateDrop: () => false,
            },
        });

        await flushPromises();

        await dragDrop1.trigger('mousedown', {
            buttons: 1,
        });
        await dragDrop2.trigger('touchmove', {
            touches: [
                {
                    pageX: 120,
                    pageY: 60,
                },
            ],
        });

        currentDragElement = getCurrentDragElement();

        expect(currentDragElement.style.left).toBe('120px');
        expect(currentDragElement.style.top).toBe('60px');
        expect(currentDragElement.style.width).toBe('100px');
        expect(currentDragElement.classList.contains('is--drag-element')).toBe(true);
        expect(dragDrop2.classes('is--invalid-drop')).toBe(true);
    });
});
