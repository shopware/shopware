/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

import 'src/app/component/form/select/base/sw-single-select';

const createWrapper = async (props = {}, attrs = {}) => {
    const wrapper = mount(await wrapTestComponent('sw-select-base', { sync: true }), {
        props,
        attrs,
        global: {
            stubs: {
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
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
    it('should show the clearable icon by default when required is not set', async () => {
        const wrapper = await createWrapper();

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(true);
    });

    it('should not show the clearable icon by default when required is true', async () => {
        const wrapper = await createWrapper({}, { required: true });

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(false);
    });

    it('should show the clearable icon when required is false', async () => {
        const wrapper = await createWrapper({}, { required: false });

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(true);
    });

    it('should show the clearable icon when explicitly set to true even if required', async () => {
        const wrapper = await createWrapper({ showClearableButton: true }, { required: true });

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(true);
    });

    it('should not show the clearable icon when explicitly set to false even if not required', async () => {
        const wrapper = await createWrapper({ showClearableButton: false }, { required: false });

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(false);
    });

    it('should trigger clear event when user clicks on clearable icon', async () => {
        const wrapper = await createWrapper({ showClearableButton: true });

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');

        // expect no clear event
        expect(wrapper.emitted('clear')).toBeUndefined();

        // click on clear
        await clearableIcon.trigger('click');

        // expect clear event thrown
        expect(wrapper.emitted('clear')).toHaveLength(1);
    });
});
