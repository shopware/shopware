/* eslint-disable sw-test-rules/test-file-max-lines-warning */

import { mount } from '@vue/test-utils';

/**
 * @sw-package discovery
 */
async function createWrapper(customPropsData = {}) {
    return mount(
        await wrapTestComponent('sw-multi-snippet-drag-and-drop', {
            sync: true,
        }),
        {
            global: {
                directives: {
                    tooltip: {},
                    droppable: {
                        mounted(el, binding) {
                            if (typeof binding.value?.data?.targetIndex === 'number') {
                                el.dataset.dropTargetIndex = String(binding.value.data.targetIndex);
                            }
                        },
                    },
                    draggable: {},
                },
                stubs: {
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-label': await wrapTestComponent('sw-label'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-context-button': {
                        template: '<div class="sw-context-button"><slot></slot></div>',
                    },
                    'sw-context-menu-item': {
                        template: `
                    <div class="sw-context-menu-item" @click="$emit('click', $event.target.value)">
                        <slot></slot>
                    </div>`,
                    },
                    'sw-inheritance-switch': true,
                    'sw-color-badge': true,
                    'sw-loader': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                },
                mocks: {
                    $t: (key) => key,
                    $route: {
                        params: {
                            id: 'id',
                        },
                    },
                    $device: {
                        getSystemKey: () => {},
                        onResize: () => {},
                    },
                },
            },

            props: {
                value: [
                    'address/company',
                    'symbol/dash',
                    'address/department',
                ],
                totalLines: 3,
                linePosition: 0,
                ...customPropsData,
            },
        },
    );
}

describe('src/module/sw-settings-country/component/sw-multi-snippet-drag-and-drop', () => {
    it('should emit `open-snippet-modal` when add new snippet', async () => {
        const wrapper = await createWrapper();

        const menuContextButton = wrapper.findAll('.sw-context-menu-item').at(0);

        await menuContextButton.trigger('click');

        expect(wrapper.emitted()['open-snippet-modal']).toBeTruthy();
    });

    it('should emit `add-new-line` when adding a new row above', async () => {
        const wrapper = await createWrapper();

        const menuContextButton = wrapper.findAll('.sw-context-menu-item').at(1);

        await menuContextButton.trigger('click');

        expect(wrapper.emitted()['add-new-line']).toBeTruthy();
        expect(wrapper.emitted()['add-new-line'][0]).toEqual([
            0,
            'above',
        ]);
    });

    it('should emit `add new line` when adding a new row below', async () => {
        const wrapper = await createWrapper();

        const menuContextButton = wrapper.findAll('.sw-context-menu-item').at(2);

        await menuContextButton.trigger('click');

        expect(wrapper.emitted()['add-new-line']).toBeTruthy();
        expect(wrapper.emitted()['add-new-line'][0]).toEqual([
            0,
            'below',
        ]);
    });

    it('should emit `location move` when move row to top', async () => {
        const wrapper = await createWrapper();

        const menuContextButton = wrapper.findAll('.sw-context-menu-item').at(3);

        await menuContextButton.trigger('click');

        expect(wrapper.emitted()['position-move']).toBeTruthy();
        expect(wrapper.emitted()['position-move'][0]).toEqual([
            0,
            0,
        ]);
    });

    it('should emit `location move` when move row to bottom', async () => {
        const wrapper = await createWrapper();

        const menuContextButton = wrapper.findAll('.sw-context-menu-item').at(4);

        await menuContextButton.trigger('click');

        expect(wrapper.emitted()['position-move']).toBeTruthy();
        expect(wrapper.emitted()['position-move'][0]).toEqual([
            0,
            null,
        ]);
    });

    it('should emit `change` when delete current line', async () => {
        const wrapper = await createWrapper();

        const menuContextButton = wrapper.findAll('.sw-context-menu-item').at(5);

        await menuContextButton.trigger('click');

        expect(wrapper.emitted('update:value')).toBeTruthy();
        expect(wrapper.emitted('update:value')[0]).toEqual([0]);
    });

    it('should emit `change` when dismiss value in selection', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const button = wrapper.find('.sw-select-selection-list__item-holder--0 > span');

        await button.find('.sw-label__dismiss').trigger('click');

        expect(wrapper.emitted('update:value')).toBeTruthy();
        expect(wrapper.emitted('update:value')[0]).toEqual([
            0,
            [
                'symbol/dash',
                'address/department',
            ],
        ]);
    });

    it('should render the selection input inside the expected wrapper', async () => {
        const wrapper = await createWrapper({ isSnippetDragging: true });
        await flushPromises();

        expect(wrapper.find('.sw-label').classes()).toContain('sw-multi-snippet-drag-and-drop__snippet');

        const inputWrapper = wrapper.find('.sw-select-selection-list__input-wrapper');
        const input = inputWrapper.find('.sw-select-selection-list__input');

        expect(input.exists()).toBe(true);
        expect(input.attributes('readonly')).toBeDefined();
        expect(input.attributes('placeholder')).toBe('sw-settings-country.general.actions.newSnippet');
        expect(inputWrapper.find('.sw-multi-snippet-drag-and-drop__add-icon').exists()).toBe(true);
        expect(inputWrapper.attributes('data-drop-target-index')).toBe('3');
        expect(wrapper.classes()).toContain('is--dragging-snippet');
    });

    it('should emit `open-snippet-modal` when clicking the selection input row', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-select-selection-list__input-wrapper').trigger('click');

        expect(wrapper.emitted('open-snippet-modal')).toEqual([[0]]);
    });

    it('should render a snippet placeholder for an empty row', async () => {
        const wrapper = await createWrapper({
            value: [],
            externalDragPreview: {
                dragIndex: 0,
                sourceLinePosition: 0,
                linePosition: 1,
                targetIndex: 0,
                snippet: 'address/company',
            },
            linePosition: 1,
        });
        await flushPromises();

        const rowItems = wrapper.findAll('.sw-select-selection-list > li');

        expect(rowItems[0].classes()).toContain('sw-multi-snippet-drag-and-drop__placeholder');
        expect(rowItems[1].classes()).toContain('sw-select-selection-list__input-wrapper');
    });

    it('should move snippets on the same line when dragging', async () => {
        const wrapper = await createWrapper({
            value: [
                'address/company',
                'symbol/dash',
                'address/department',
                'address/city',
            ],
        });
        await flushPromises();

        expect(wrapper.vm.value[1]).toBe('symbol/dash');
        expect(wrapper.vm.value[0]).toBe('address/company');

        await wrapper.vm.onDrop(
            {
                index: 0,
                linePosition: 0,
                snippet: 'address/company',
            },
            {
                index: 2,
                linePosition: 0,
                snippet: 'address/department',
            },
        );
        await flushPromises();

        expect(wrapper.emitted('update:value')).toBeTruthy();
        expect(wrapper.emitted('update:value')[0]).toEqual([
            0,
            [
                'symbol/dash',
                'address/department',
                'address/company',
                'address/city',
            ],
        ]);
    });

    it('should preview the dragged snippet position while dragging', async () => {
        const wrapper = await createWrapper({
            value: [
                'address/company',
                'symbol/dash',
                'address/department',
                'address/city',
            ],
        });
        await flushPromises();

        await wrapper.vm.onDragEnter(
            {
                index: 0,
                linePosition: 0,
                snippet: 'address/company',
            },
            {
                index: 2,
                linePosition: 0,
                snippet: 'address/department',
                targetIndex: 3,
            },
        );

        expect(wrapper.vm.dragPreviewSnippet).toBe('address/company');
        expect(wrapper.vm.isDragPreviewSource(0)).toBe(true);
        expect(wrapper.vm.shouldShowPlaceholderBefore(3)).toBe(true);
        expect(wrapper.vm.shouldShowPlaceholderAfter(2)).toBe(false);

        await wrapper.vm.onDragEnter(
            {
                index: 0,
                linePosition: 0,
                snippet: 'address/company',
            },
            {
                index: 1,
                linePosition: 0,
                snippet: 'symbol/dash',
                targetIndex: 1,
            },
        );

        expect(wrapper.vm.dragPreviewSnippet).toBe('address/company');
        expect(wrapper.vm.shouldShowPlaceholderBefore(1)).toBe(true);
        expect(wrapper.vm.shouldShowPlaceholderAfter(1)).toBe(false);

        await wrapper.vm.onDrop(
            {
                index: 0,
                linePosition: 0,
                snippet: 'address/company',
            },
            {
                index: 1,
                linePosition: 0,
                snippet: 'symbol/dash',
                targetIndex: 1,
            },
        );

        expect(wrapper.vm.hasDragPreview).toBe(false);
        expect(wrapper.emitted('update:value')[0]).toEqual([
            0,
            [
                'address/company',
                'symbol/dash',
                'address/department',
                'address/city',
            ],
        ]);
    });

    it('should preview the original position after reversing across the hidden dragged snippet', async () => {
        const wrapper = await createWrapper({
            value: [
                'address/company',
                'symbol/dash',
                'address/department',
                'address/city',
            ],
        });
        await flushPromises();

        await wrapper.vm.onDragEnter(
            {
                index: 1,
                linePosition: 0,
                snippet: 'symbol/dash',
            },
            {
                index: 2,
                linePosition: 0,
                snippet: 'address/department',
                targetIndex: 3,
            },
        );

        expect(wrapper.vm.isDragPreviewSource(1)).toBe(true);
        expect(wrapper.vm.shouldShowPlaceholderBefore(3)).toBe(true);

        await wrapper.vm.onDragEnter(
            {
                index: 1,
                linePosition: 0,
                snippet: 'symbol/dash',
            },
            {
                index: 0,
                linePosition: 0,
                snippet: 'address/company',
                targetIndex: 1,
            },
        );

        expect(wrapper.vm.shouldShowPlaceholderBefore(1)).toBe(true);
        expect(wrapper.vm.shouldShowPlaceholderAfter(0)).toBe(false);

        await wrapper.vm.onDrop(
            {
                index: 1,
                linePosition: 0,
                snippet: 'symbol/dash',
            },
            {
                index: 0,
                linePosition: 0,
                snippet: 'address/company',
                targetIndex: 1,
            },
        );

        expect(wrapper.emitted('update:value')[0]).toEqual([
            0,
            [
                'address/company',
                'symbol/dash',
                'address/department',
                'address/city',
            ],
        ]);
    });

    it('should update the drop position when entering the same snippet twice', async () => {
        const wrapper = await createWrapper({
            value: [
                'address/company',
                'symbol/dash',
                'address/department',
                'address/city',
            ],
        });
        await flushPromises();

        await wrapper.vm.onDragEnter(
            {
                index: 0,
                linePosition: 0,
                snippet: 'address/company',
            },
            {
                index: 2,
                linePosition: 0,
                snippet: 'address/department',
                targetIndex: 2,
            },
        );

        expect(wrapper.vm.shouldShowPlaceholderBefore(2)).toBe(true);
        expect(wrapper.vm.shouldShowPlaceholderBefore(3)).toBe(false);

        await wrapper.vm.onDragEnter(
            {
                index: 0,
                linePosition: 0,
                snippet: 'address/company',
            },
            {
                index: 2,
                linePosition: 0,
                snippet: 'address/department',
                targetIndex: 3,
            },
        );

        expect(wrapper.vm.shouldShowPlaceholderBefore(2)).toBe(false);
        expect(wrapper.vm.shouldShowPlaceholderBefore(3)).toBe(true);

        await wrapper.vm.onDrop(
            {
                index: 0,
                linePosition: 0,
                snippet: 'address/company',
            },
            null,
        );

        expect(wrapper.emitted('update:value')[0]).toEqual([
            0,
            [
                'symbol/dash',
                'address/department',
                'address/company',
                'address/city',
            ],
        ]);
    });

    it('should disable "delete item" menu context if totalLines is equal or less than default min lines', async () => {
        const wrapper = await createWrapper({ totalLines: 1 });

        const menuContextButton = wrapper.findAll('.sw-context-menu-item').at(5);

        expect(menuContextButton.attributes().disabled).toBeDefined();
    });

    it('should disabled "add new item" context menu item if totalLines is equal or higher than default max lines', async () => {
        const wrapper = await createWrapper({ totalLines: 10 });

        const menuContextButton = wrapper.findAll('.sw-context-menu-item').at(0);

        expect(menuContextButton.attributes().disabled).toBeDefined();
    });

    it('should emit event `drag-start` when starting drag', async () => {
        const wrapper = await createWrapper({ totalLines: 1 });
        await flushPromises();

        await wrapper.vm.onDragStart();
        await flushPromises();

        expect(wrapper.emitted()['drag-start']).toBeTruthy();
    });

    it('should emit event `drag-enter` when ending drag', async () => {
        const wrapper = await createWrapper({ totalLines: 1 });

        await wrapper.vm.onDragEnter(null, null);
        expect(wrapper.emitted()['drag-enter']).toBeFalsy();

        await wrapper.vm.onDragEnter({ data: {} }, { data: {} });
        expect(wrapper.emitted()['drag-enter']).toBeTruthy();
    });

    it('should emit event `drop-end` when drop', async () => {
        const wrapper = await createWrapper({ totalLines: 1 });

        await wrapper.vm.onDragEnter(null, null);
        expect(wrapper.emitted()['drag-enter']).toBeFalsy();

        await wrapper.vm.onDragEnter(
            {
                index: 0,
                linePosition: 1,
                snippet: 'address/company',
            },
            {
                index: 1,
                linePosition: 0,
                snippet: 'symbol/dash',
                targetIndex: 1,
            },
        );

        await wrapper.vm.onDrop(
            {
                index: 0,
                linePosition: 1,
                snippet: 'address/company',
            },
            null,
        );

        expect(wrapper.emitted()['drop-end']).toBeTruthy();
        expect(wrapper.emitted()['drop-end'][0][1].dropData.targetIndex).toBe(1);
    });
});
