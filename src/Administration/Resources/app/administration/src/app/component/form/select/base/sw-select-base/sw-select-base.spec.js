/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

import 'src/app/component/form/select/base/sw-single-select';

const createWrapper = async () => {
    const wrapper = mount(await wrapTestComponent('sw-select-base', { sync: true }), {
        global: {
            stubs: {
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-icon': {
                    template: '<div @click="$emit(\'click\', $event)"></div>',
                },
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-help-text': true,
                'sw-ai-copilot-badge': true,
                'sw-inheritance-switch': true,
                'sw-loader': true,
            },
        },
    });

    await flushPromises();

    return wrapper;
};

describe('components/sw-select-base', () => {
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
