/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

async function createWrapper(props = {}) {
    return mount(
        await wrapTestComponent('sw-sales-channel-detail-agentic-commerce-integration', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'mt-card': {
                        template: '<div class="mt-card"><slot></slot></div>',
                    },
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'mt-text-field': {
                        template: '<input class="mt-text-field" />',
                        props: [
                            'modelValue',
                            'label',
                            'disabled',
                            'copyable',
                        ],
                    },
                },
            },
            props: {
                salesChannel: {
                    typeId: Shopware.Defaults.agenticCommerceTypeId,
                },
                productExport: {
                    provider: 'open-ai',
                },
                productComparisonAccessUrl: 'https://example.com/store-api/product-export/key/file.jsonl',
                isLoading: false,
                ...props,
            },
        },
    );
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-agentic-commerce-integration', () => {
    it('should not render the card while loading', async () => {
        const wrapper = await createWrapper({ isLoading: true });

        expect(wrapper.find('.mt-card').exists()).toBe(false);
    });

    it('should render the feed URL field when a feed URL exists', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.findComponent('.mt-text-field');

        expect(field.exists()).toBe(true);
        expect(field.props('disabled')).toBe(true);
        expect(field.props('copyable')).toBe(true);
    });

    it('should not render the feed URL field when no feed URL exists', async () => {
        const wrapper = await createWrapper({
            productComparisonAccessUrl: '',
        });

        expect(wrapper.findComponent('.mt-text-field').exists()).toBe(false);
    });
});
