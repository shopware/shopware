/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';
import swAgenticCommerceTrackingConfig from './index';

Shopware.Component.register('sw-agentic-commerce-tracking-config', swAgenticCommerceTrackingConfig);

async function createWrapper(salesChannelOverride = {}) {
    return mount(await Shopware.Component.build('sw-agentic-commerce-tracking-config'), {
        props: {
            salesChannel: {
                configuration: {},
                ...salesChannelOverride,
            },
        },
        global: {
            stubs: {
                'mt-card': { template: '<div><slot /></div>' },
                'mt-text-field': {
                    template:
                        '<input type="text" :disabled="disabled || undefined" :value="modelValue" @input="$emit(\'update:model-value\', $event.target.value)" />',
                    props: [
                        'modelValue',
                        'disabled',
                    ],
                },
            },
        },
    });
}

describe('sw-agentic-commerce-tracking-config', () => {
    it('renders affiliate and campaign fields enabled by default', async () => {
        const wrapper = await createWrapper();

        const inputs = wrapper.findAll('input[type="text"]');
        expect(inputs).toHaveLength(2);
        expect(inputs[0].attributes('disabled')).toBeUndefined();
        expect(inputs[1].attributes('disabled')).toBeUndefined();
    });

    it('emits change with affiliateCode when affiliate field changes', async () => {
        const wrapper = await createWrapper();

        const inputs = wrapper.findAll('input[type="text"]');
        await inputs[0].setValue('my-channel');

        expect(wrapper.emitted('change')).toBeTruthy();
        expect(wrapper.emitted('change')[0][0].affiliateCode).toBe('my-channel');
    });

    it('emits change with campaignCode when campaign field changes', async () => {
        const wrapper = await createWrapper();

        const inputs = wrapper.findAll('input[type="text"]');
        await inputs[1].setValue('spring-2025');

        expect(wrapper.emitted('change')).toBeTruthy();
        expect(wrapper.emitted('change')[0][0].campaignCode).toBe('spring-2025');
    });

    it('disables all inputs when disabled prop is true', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({ disabled: true });

        const inputs = wrapper.findAll('input[type="text"]');
        expect(inputs[0].attributes('disabled')).toBeDefined();
        expect(inputs[1].attributes('disabled')).toBeDefined();
    });
});
