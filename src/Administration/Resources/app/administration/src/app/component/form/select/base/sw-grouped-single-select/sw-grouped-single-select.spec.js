/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const createWrapper = async (customOptions = {}) => {
    const wrapper = mount(await wrapTestComponent('sw-grouped-single-select', { sync: true }), {
        global: {
            directives: {
                popover: Shopware.Directive.getDirectiveRegistry().get('popover'),
            },
            stubs: {
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
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

        const resultList = document.body.querySelector('.sw-select-result-list__content');
        expect(resultList).toBeTruthy();
    });

    it('should show the results items and groups', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const listElements = document.body.querySelectorAll('.sw-select-result-list__item-list li');

        expect(listElements.item(0).textContent.trim()).toBe('Group 1');
        expect(listElements.item(1).textContent.trim()).toBe('Entry 1');
        expect(listElements.item(2).textContent.trim()).toBe('Entry 2');
        expect(listElements.item(3).textContent.trim()).toBe('Group 2');
        expect(listElements.item(4).textContent.trim()).toBe('Entry 3');
    });

    it('should close the result list after clicking an item', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-select__selection').trigger('click');
        await flushPromises();

        document.body.querySelector('.sw-select-option--0').click();
        await flushPromises();

        const resultList = document.body.querySelector('.sw-select-result-list__content');
        expect(resultList).toBeFalsy();
    });

    it('should not close the result list after clicking a group', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-select__selection').trigger('click');
        await flushPromises();

        document.body.querySelector('.sw-grouped-single-select__group-separator').click();
        await flushPromises();

        const resultList = document.body.querySelector('.sw-select-result-list__content');
        expect(resultList).toBeTruthy();
    });
});
