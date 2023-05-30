/**
 * @package admin
 */

import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/form/select/base/sw-grouped-single-select';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/base/sw-highlight-text';

const createSelect = async (customOptions) => {
    const localVue = createLocalVue();
    localVue.directive('popover', {});

    const options = {
        localVue,
        stubs: {
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-icon': {
                template: '<div @click="$emit(\'click\', $event)"></div>',
            },
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-select-result': await Shopware.Component.build('sw-select-result'),
            'sw-highlight-text': await Shopware.Component.build('sw-highlight-text'),
        },
        propsData: {
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
    };

    return shallowMount(await Shopware.Component.build('sw-grouped-single-select'), {
        ...options,
        ...customOptions,
    });
};

describe('components/sw-grouped-single-select', () => {
    it('should be a Vue.js component', async () => {
        const swGroupedSingleSelect = await createSelect();

        expect(swGroupedSingleSelect.vm).toBeTruthy();
    });

    it('should open the result list on click on .sw-select__selection', async () => {
        const swGroupedSingleSelect = await createSelect();
        await swGroupedSingleSelect.find('.sw-select__selection').trigger('click');

        const resultList = swGroupedSingleSelect.find('.sw-select-result-list__content');
        expect(resultList.isVisible()).toBeTruthy();
    });

    it('should show the results items and groups', async () => {
        const swGroupedSingleSelect = await createSelect();
        await swGroupedSingleSelect.find('.sw-select__selection').trigger('click');

        const listElements = swGroupedSingleSelect.findAll('.sw-select-result-list__item-list li');

        expect(listElements.at(0).text()).toBe('Group 1');
        expect(listElements.at(1).text()).toBe('Entry 1');
        expect(listElements.at(2).text()).toBe('Entry 2');
        expect(listElements.at(3).text()).toBe('Group 2');
        expect(listElements.at(4).text()).toBe('Entry 3');
    });

    it('should close the result list after clicking an item', async () => {
        const swGroupedSingleSelect = await createSelect();

        await swGroupedSingleSelect.find('.sw-select__selection').trigger('click');
        await swGroupedSingleSelect.find('.sw-select-option--0').trigger('click');

        const resultList = swGroupedSingleSelect.find('.sw-select-result-list__content');
        expect(resultList.exists()).toBeFalsy();
    });

    it('should not close the result list after clicking a group', async () => {
        const swGroupedSingleSelect = await createSelect();

        await swGroupedSingleSelect.find('.sw-select__selection').trigger('click');
        await swGroupedSingleSelect.find('.sw-grouped-single-select__group-separator').trigger('click');

        const resultList = swGroupedSingleSelect.find('.sw-select-result-list__content');
        expect(resultList.exists()).toBeTruthy();
    });
});
