/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils_v2';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';

const createWrapper = async (customOptions) => {
    return shallowMount(await Shopware.Component.build('sw-select-base'), {
        stubs: {
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-icon': {
                template: '<div @click="$emit(\'click\', $event)"></div>',
            },
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
        },
        ...customOptions,
    });
};

describe('components/sw-select-base', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not show the clearable icon in the select base when prop is not set', async () => {
        const wrapper = await createWrapper();

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(false);
    });

    it('should show the clearable icon in the select base when prop is set', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            showClearableButton: true,
        });

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.isVisible()).toBe(true);
    });

    it('should trigger clear event when user clicks on clearable icon', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            showClearableButton: true,
        });

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');

        // expect no clear event
        expect(wrapper.emitted('clear')).toBeUndefined();

        // click on clear
        await clearableIcon.trigger('click');

        // expect clear event thrown
        expect(wrapper.emitted('clear')).toHaveLength(1);
    });
});
