import { createLocalVue, shallowMount, enableAutoDestroy } from '@vue/test-utils';

import 'src/module/sw-settings-country/component/sw-multi-snippet-drag-and-drop';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/base/sw-label';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/base/sw-button';

function createWrapper(customPropsData = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.directive('droppable', {});
    localVue.directive('draggable', {});

    return shallowMount(Shopware.Component.build('sw-multi-snippet-drag-and-drop'), {
        localVue,

        mocks: {
            $tc: key => key,
            $route: {
                params: {
                    id: 'id'
                }
            },
            $device: {
                getSystemKey: () => {},
                onResize: () => {}
            }
        },

        propsData: {
            value: [
                { value: 'address/company', type: 'snippet' },
                { value: '-', type: 'plain' },
                { value: 'address/department', type: 'snippet' },
            ],
            linePosition: 0,
            ...customPropsData
        },

        stubs: {
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-label': Shopware.Component.build('sw-label'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-context-button': {
                template: '<div class="sw-context-button"><slot></slot></div>'
            },
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-context-menu-item': {
                template: `
                    <div class="sw-context-menu-item" @click="$emit('click', $event.target.value)">
                        <slot></slot>
                    </div>`
            },
            'sw-icon': true,
        }
    });
}

enableAutoDestroy(afterEach);

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

        expect(wrapper.emitted()['location-move']).toBeTruthy();
        expect(wrapper.emitted()['location-move'][0]).toEqual([0, 0]);
    });

    it('should emit `location move` when move row to bottom', async () => {
        const wrapper = await createWrapper();

        const menuContextButton = wrapper.findAll('.sw-context-menu-item').at(4);

        await menuContextButton.trigger('click');

        expect(wrapper.emitted()['location-move']).toBeTruthy();
        expect(wrapper.emitted()['location-move'][0]).toEqual([0, null]);
    });

    it('should emit `change` when delete current line', async () => {
        const wrapper = await createWrapper();

        const menuContextButton = wrapper.findAll('.sw-context-menu-item').at(5);

        await menuContextButton.trigger('click');

        expect(wrapper.emitted('change')).toBeTruthy();
        expect(wrapper.emitted('change')[0]).toEqual([0]);
    });

    it('should emit `change` when dismiss value in selection', async () => {
        const wrapper = await createWrapper();

        const button = wrapper.find('.sw-select-selection-list__item-holder--0 > span');

        await button.find('.sw-label__dismiss').trigger('click');

        expect(wrapper.emitted('change')).toBeTruthy();
        expect(wrapper.emitted('change')[0]).toEqual([
            0,
            [
                { value: '-', type: 'plain' },
                {
                    type: 'snippet',
                    value: 'address/department'
                }
            ]
        ]);
    });

    it('should emit `change` when swap on the same line on dragging', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.value[1]).toEqual({ type: 'plain', value: '-' });
        expect(wrapper.vm.value[0])
            .toEqual({ type: 'snippet', value: 'address/company' });

        await wrapper.vm.dragEnd(
            {
                index: 0,
                linePosition: 0,
                snippet: {
                    type: 'snippet',
                    value: 'address/company'
                }
            }, {
                index: 1,
                linePosition: 0,
                snippet: { type: 'plain', value: '-' }
            },
        );

        expect(wrapper.emitted('change')).toBeTruthy();
        expect(wrapper.emitted('change')[0]).toEqual([
            0,
            [
                { value: '-', type: 'plain' },
                { value: 'address/company', type: 'snippet' },
                { value: 'address/department', type: 'snippet' }
            ]
        ]);
    });
});
