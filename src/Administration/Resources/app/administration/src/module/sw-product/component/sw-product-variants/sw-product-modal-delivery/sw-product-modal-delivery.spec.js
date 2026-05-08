/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-modal-delivery', { sync: true }), {
        props: {
            product: {},
            selectedGroups: [],
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => ({ id: 'id' }),
                        save: () => Promise.resolve({}),
                    }),
                },
                shortcutService: {
                    startEventListener: () => {},
                    stopEventListener: () => {},
                },
            },
            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-tabs': {
                    name: 'sw-tabs',
                    template: '<div class="sw-tabs"><slot /></div>',
                },
                'sw-tabs-item': true,
                'mt-tabs': {
                    name: 'mt-tabs',
                    props: {
                        items: {
                            type: Array,
                            required: true,
                        },
                        defaultItem: {
                            type: String,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: true,
                        },
                        vertical: {
                            type: Boolean,
                            required: false,
                            default: false,
                        },
                    },
                    template:
                        '<mt-tabs-stub :items="items" :default-item="defaultItem" :position-identifier="positionIdentifier" :vertical="vertical" @new-item-active="$emit(\'new-item-active\', $event)" />',
                },
                'sw-product-variants-delivery-order': true,
                'sw-product-variants-delivery-media': true,
                'sw-product-variants-delivery-listing': true,
                'sw-loader': true,
                'router-link': true,
            },
        },
    });
}

describe('src/module/sw-product/component/sw-product-variants/sw-product-modal-delivery', () => {
    afterEach(() => {
        global.activeFeatureFlags = [];
    });

    it('renders legacy tabs when the major migration is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('renders mt-tabs with delivery items when the major migration is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const mtTabs = wrapper.getComponent('mt-tabs-stub');

        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
        expect(mtTabs.props('positionIdentifier')).toBe('sw-product-modal-delivery');
        expect(mtTabs.props('vertical')).toBe(true);
        expect(mtTabs.props('defaultItem')).toBe('order');
        expect(mtTabs.props('items')).toStrictEqual([
            {
                label: 'sw-product.variations.deliveryModal.order',
                name: 'order',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-product.variations.deliveryModal.media',
                name: 'media',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-product.variations.deliveryModal.listing',
                name: 'listing',
                onClick: expect.any(Function),
            },
        ]);
    });

    it('normalizes mt-tabs active item payloads for delivery tabs', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();

        wrapper.getComponent('mt-tabs-stub').vm.$emit('new-item-active', { name: 'media' });

        expect(wrapper.vm.activeTab).toBe('media');
    });

    it('should have an disabled save button', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        const saveButton = wrapper.find('.sw-product-modal-delivery__save-button');

        expect(saveButton.exists()).toBeTruthy();
        expect(saveButton.attributes('disabled')).toBeDefined();
    });

    it('should have an enabled save button', async () => {
        global.activeAclRoles = ['product.editor'];
        const wrapper = await createWrapper([
            'product.editor',
        ]);
        await flushPromises();

        const saveButton = wrapper.find('.sw-product-modal-delivery__save-button');

        expect(saveButton.exists()).toBeTruthy();
        expect(saveButton.attributes('disabled')).toBeUndefined();
    });

    it('should be able to allow save storefront presentation modal', async () => {
        global.activeAclRoles = ['product.editor'];
        const wrapper = await createWrapper([
            'product.editor',
        ]);
        await flushPromises();
        const saveButton = wrapper.find('.sw-product-modal-delivery__save-button');

        expect(saveButton.exists()).toBeTruthy();
        expect(saveButton.attributes('disabled')).toBeUndefined();
        await saveButton.trigger('click');
        const emitted = wrapper.emitted()['configuration-close'];
        expect(emitted).toBeTruthy();
    });
});
