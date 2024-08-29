/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

const createWrapper = async (customOptions = {}) => {
    const wrapper = mount(await wrapTestComponent('sw-grouped-single-select', { sync: true }), {
        global: {
            stubs: {
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-icon': {
                    template: '<div @click="$emit(\'click\', $event)"></div>',
                },
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
        },
        props: {
            value: null,
            options: [
                {
                    label: 'Entry 1',
                    value: 'entryOneValue',
                    group: 'group1',
                },
                {
                    label: 'Entry 2',
                    value: 'entryTwoValue',
                    group: 'group1',
                },
                {
                    label: 'Entry 3',
                    value: 'entryThreeValue',
                    group: 'group2',
                },
            ],
            groups: [
                {
                    id: 'group1',
                    label: 'Group 1',
                },
                {
                    id: 'group2',
                    label: 'Group 2',
                },
            ],
        },
        ...customOptions,
    });

    await flushPromises();

    return wrapper;
};

describe('components/sw-grouped-single-select', () => {
    it('should open the result list on click on .sw-select__selection', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const resultList = wrapper.find('.sw-select-result-list__content');
        expect(resultList.isVisible()).toBeTruthy();
    });

    it('should show the results items and groups', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const listElements = wrapper.findAll('.sw-select-result-list__item-list li');

        expect(listElements.at(0).text()).toBe('Group 1');
        expect(listElements.at(1).text()).toBe('Entry 1');
        expect(listElements.at(2).text()).toBe('Entry 2');
        expect(listElements.at(3).text()).toBe('Group 2');
        expect(listElements.at(4).text()).toBe('Entry 3');
    });

    it('should close the result list after clicking an item', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        const resultList = wrapper.find('.sw-select-result-list__content');
        expect(resultList.exists()).toBeFalsy();
    });

    it('should not close the result list after clicking a group', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-grouped-single-select__group-separator').trigger('click');
        await flushPromises();

        const resultList = wrapper.find('.sw-select-result-list__content');
        expect(resultList.exists()).toBe(true);
    });
});
