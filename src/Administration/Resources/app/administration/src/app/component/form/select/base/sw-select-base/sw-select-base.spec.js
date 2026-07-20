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

    it('should not collapse when the event target is outside but the click position is inside the select', async () => {
        const wrapper = await createWrapper();
        const selection = wrapper.find('.sw-select__selection').element;
        const originalElementsFromPoint = document.elementsFromPoint;

        try {
            document.elementsFromPoint = jest.fn(() => [
                selection,
                document.body,
            ]);
            wrapper.vm.collapse = jest.fn();

            wrapper.vm.listenToClickOutside({
                target: document.body,
                clientX: 10,
                clientY: 10,
            });

            expect(wrapper.vm.collapse).not.toHaveBeenCalled();
        } finally {
            document.elementsFromPoint = originalElementsFromPoint;
        }
    });

    it('should collapse when the event target and click position are outside the select', async () => {
        const wrapper = await createWrapper();
        const originalElementsFromPoint = document.elementsFromPoint;

        try {
            document.elementsFromPoint = jest.fn(() => [
                document.body,
            ]);
            wrapper.vm.collapse = jest.fn();

            wrapper.vm.listenToClickOutside({
                target: document.body,
                clientX: 10,
                clientY: 10,
            });

            expect(wrapper.vm.collapse).toHaveBeenCalledTimes(1);
        } finally {
            document.elementsFromPoint = originalElementsFromPoint;
        }
    });
});
