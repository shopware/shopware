import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-tabs-item';
import 'src/app/component/base/sw-icon';

async function createWrapper(propsData = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {
        bind(el, binding) {
            el.setAttribute('data-tooltip-message', binding.value.message);
            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
        },
        inserted(el, binding) {
            el.setAttribute('data-tooltip-message', binding.value.message);
            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
        },
        update(el, binding) {
            el.setAttribute('data-tooltip-message', binding.value.message);
            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
        },
    });

    return shallowMount(await Shopware.Component.build('sw-tabs-item'), {
        propsData,

        localVue,

        stubs: {
            'sw-icon': await Shopware.Component.build('sw-icon'),
        },
    });
}

describe('component/base/sw-tabs-item', () => {
    it('should not have an error or warning state', async () => {
        const wrapper = await createWrapper();

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

        expect(wrapper.classes()).toContain('sw-tabs-item--has-error');

        const errorIcon = wrapper.find('.sw-tabs-item__error-badge');
        expect(errorIcon.isVisible()).toBe(true);
        expect(errorIcon.find('[data-testid="sw-icon__solid-exclamation-circle"]').exists()).toBe(true);
        expect(errorIcon.attributes('data-tooltip-message')).toBe('Custom error message');
    });

    it('should have a warning state', async () => {
        const wrapper = await createWrapper({
            hasWarning: true,
            warningTooltip: 'Custom warning message',
        });

        expect(wrapper.classes()).toContain('sw-tabs-item--has-warning');

        const warningIcon = wrapper.find('.sw-tabs-item__warning-badge');
        expect(warningIcon.isVisible()).toBe(true);
        expect(warningIcon.find('[data-testid="sw-icon__solid-exclamation-triangle"]').exists()).toBe(true);
        expect(warningIcon.attributes('data-tooltip-message')).toBe('Custom warning message');
    });
});
