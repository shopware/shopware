import { mount } from '@vue/test-utils';

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-tabs-item', { sync: true }), {
        props,
        global: {
            stubs: {
                'sw-icon': await wrapTestComponent('sw-icon'),
                'sw-icon-deprecated': await wrapTestComponent('sw-icon-deprecated'),
                'router-link': true,
                'mt-icon': true,
            },
            directives: {
                tooltip: {
                    beforeMount(el, binding) {
                        el.setAttribute('data-tooltip-message', binding.value.message);
                        el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                    },
                    mounted(el, binding) {
                        el.setAttribute('data-tooltip-message', binding.value.message);
                        el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                    },
                    updated(el, binding) {
                        el.setAttribute('data-tooltip-message', binding.value.message);
                        el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                    },
                },
            },
        },
    });
}

describe('component/base/sw-tabs-item', () => {
    it('should not have an error or warning state', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const errorIcon = wrapper.find('.sw-tabs-item__error-badge');
        expect(errorIcon.exists()).toBe(false);

        const warningIcon = wrapper.find('.sw-tabs-item__warning-badge');
        expect(warningIcon.exists()).toBe(false);
    });

    it('should have an error state', async () => {
        const wrapper = await createWrapper({
            hasError: true,
            errorTooltip: 'Custom error message',
        });
        await flushPromises();

        expect(wrapper.classes()).toContain('sw-tabs-item--has-error');

        const errorIcon = wrapper.find('.sw-tabs-item__error-badge');
        expect(errorIcon.isVisible()).toBe(true);
        expect(errorIcon.attributes('data-testid')).toBe('sw-icon__solid-exclamation-circle');
        expect(errorIcon.attributes('data-tooltip-message')).toBe('Custom error message');
    });

    it('should have a warning state', async () => {
        const wrapper = await createWrapper({
            hasWarning: true,
            warningTooltip: 'Custom warning message',
        });
        await flushPromises();

        expect(wrapper.classes()).toContain('sw-tabs-item--has-warning');

        const warningIcon = wrapper.find('.sw-tabs-item__warning-badge');
        expect(warningIcon.isVisible()).toBe(true);

        expect(warningIcon.attributes('data-testid')).toBe('sw-icon__solid-exclamation-triangle');
        expect(warningIcon.attributes('data-tooltip-message')).toBe('Custom warning message');
    });
});
