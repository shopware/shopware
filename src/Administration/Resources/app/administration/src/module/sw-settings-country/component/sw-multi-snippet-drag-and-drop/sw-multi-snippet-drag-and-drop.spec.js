import { mount } from '@vue/test-utils';

/**
 * @package customer-order
 * @group disabledCompat
 */
async function createWrapper(customPropsData = {}) {
    return mount(await wrapTestComponent('sw-multi-snippet-drag-and-drop', {
        sync: true,
    }), {
        global: {
            directives: {
                tooltip: {},
                droppable: {},
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
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-context-menu-item': {
                    template: `
                    <div class="sw-context-menu-item" @click="$emit('click', $event.target.value)">
                        <slot></slot>
                    </div>`,
                },
                'sw-icon': true,
                'sw-inheritance-switch': true,
                'sw-color-badge': true,
                'sw-loader': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
            mocks: {
                $tc: key => key,
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
            value: ['address/company', 'symbol/dash', 'address/department'],
            totalLines: 3,
            linePosition: 0,
            ...customPropsData,
        },
    });
}

describe('src/module/sw-settings-country/component/sw-multi-snippet-drag-and-drop', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

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
        expect(wrapper.emitted()['add-new-line'][0]).toEqual([0, 'above']);
    });

    it('should emit `add new line` when adding a new row below', async () => {
        const wrapper = await createWrapper();

        const menuContextButton = wrapper.findAll('.sw-context-menu-item').at(2);

        await menuContextButton.trigger('click');

        expect(wrapper.emitted()['add-new-line']).toBeTruthy();
        expect(wrapper.emitted()['add-new-line'][0]).toEqual([0, 'below']);
    });

    it('should emit `location move` when move row to top', async () => {
        const wrapper = await createWrapper();

        const menuContextButton = wrapper.findAll('.sw-context-menu-item').at(3);

        await menuContextButton.trigger('click');

        expect(wrapper.emitted()['position-move']).toBeTruthy();
        expect(wrapper.emitted()['position-move'][0]).toEqual([0, 0]);
    });

    it('should emit `location move` when move row to bottom', async () => {
        const wrapper = await createWrapper();

        const menuContextButton = wrapper.findAll('.sw-context-menu-item').at(4);

        await menuContextButton.trigger('click');

        expect(wrapper.emitted()['position-move']).toBeTruthy();
        expect(wrapper.emitted()['position-move'][0]).toEqual([0, null]);
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

        await button.find('.sw-label__dismiss')
            .trigger('click');

        expect(wrapper.emitted('update:value')).toBeTruthy();
        expect(wrapper.emitted('update:value')[0]).toEqual([
            0,
            ['symbol/dash', 'address/department'],
        ]);
    });

    it('should emit `change` when swap on the same line on dragging', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.value[1]).toBe('symbol/dash');
        expect(wrapper.vm.value[0]).toBe('address/company');

        await wrapper.vm.onDrop({
            index: 0,
            linePosition: 0,
            snippet: 'address/company',
        }, {
            index: 1,
            linePosition: 0,
            snippet: 'symbol/dash',
        });
        await flushPromises();

        expect(wrapper.emitted('update:value')).toBeTruthy();
        expect(wrapper.emitted('update:value')[0]).toEqual([
            0,
            ['symbol/dash', 'address/company', 'address/department'],
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

        await wrapper.vm.onDrop({
            index: 0,
            linePosition: 1,
            snippet: 'address/company',
        }, {
            index: 1,
            linePosition: 0,
            snippet: 'symbol/dash',
        });

        expect(wrapper.emitted()['drop-end']).toBeTruthy();
    });
});
