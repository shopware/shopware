/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';

async function createWrapper({ featureActive = false, product = {}, save = () => Promise.resolve({}) } = {}) {
    return mount(await wrapTestComponent('sw-product-modal-delivery', { sync: true }), {
        props: {
            product,
            selectedGroups: [],
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => ({ id: 'id' }),
                        save,
                    }),
                },
                feature: {
                    isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
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
                    props: {
                        isVertical: {
                            type: Boolean,
                            required: false,
                            default: false,
                        },
                        positionIdentifier: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                    },
                    template: '<div class="sw-tabs"><slot></slot></div>',
                },
                'sw-tabs-item': {
                    name: 'sw-tabs-item',
                    props: {
                        active: {
                            type: Boolean,
                            required: false,
                            default: false,
                        },
                    },
                    template: '<button class="sw-tabs-item" @click="$emit(\'click\')"><slot></slot></button>',
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    emits: ['new-item-active'],
                    props: {
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        items: {
                            type: Array,
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
                    template: '<div class="mt-tabs"></div>',
                },
                'sw-product-variants-delivery-order': {
                    template: '<div class="sw-product-variants-delivery-order"></div>',
                },
                'sw-product-variants-delivery-media': {
                    template: '<div class="sw-product-variants-delivery-media"></div>',
                },
                'sw-product-variants-delivery-listing': {
                    template: '<div class="sw-product-variants-delivery-listing"></div>',
                },
                'sw-loader': true,
                'router-link': true,
            },
        },
    });
}

describe('src/module/sw-product/component/sw-product-variants/sw-product-modal-delivery', () => {
    it('should render the fallback tabs branch while the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.getComponent({ name: 'sw-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-product-modal-delivery');
        expect(tabs.props('isVertical')).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper({ featureActive: true });
        await flushPromises();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-product-modal-delivery');
        expect(tabs.props('defaultItem')).toBe('order');
        expect(tabs.props('vertical')).toBe(true);
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-product.variations.deliveryModal.order',
                name: 'order',
            },
            {
                label: 'sw-product.variations.deliveryModal.media',
                name: 'media',
            },
            {
                label: 'sw-product.variations.deliveryModal.listing',
                name: 'listing',
            },
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
        expect(wrapper.find('.sw-product-variants-delivery-order').exists()).toBe(true);
    });

    it('should switch meteor tab content when the active tab changes', async () => {
        const wrapper = await createWrapper({ featureActive: true });
        await flushPromises();

        wrapper.getComponent({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'media');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('media');
        expect(wrapper.find('.sw-product-variants-delivery-order').exists()).toBe(false);
        expect(wrapper.find('.sw-product-variants-delivery-media').exists()).toBe(true);
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
        const wrapper = await createWrapper(['product.editor']);
        await flushPromises();

        const saveButton = wrapper.find('.sw-product-modal-delivery__save-button');

        expect(saveButton.exists()).toBeTruthy();
        expect(saveButton.attributes('disabled')).toBeUndefined();
    });

    it('should be able to allow save storefront presentation modal', async () => {
        global.activeAclRoles = ['product.editor'];
        const wrapper = await createWrapper(['product.editor']);
        await flushPromises();
        const saveButton = wrapper.find('.sw-product-modal-delivery__save-button');

        expect(saveButton.exists()).toBeTruthy();
        expect(saveButton.attributes('disabled')).toBeUndefined();
        await saveButton.trigger('click');
        const emitted = wrapper.emitted()['configuration-close'];
        expect(emitted).toBeTruthy();
    });

    describe('storefront presentation in product listings', () => {
        function createExpandedGroup() {
            return { id: 'group-id', expressionForListings: true, representation: 'box' };
        }

        async function saveVariantListingConfig(product) {
            global.activeAclRoles = ['product.editor'];
            const save = jest.fn(() => Promise.resolve({}));
            const wrapper = await createWrapper({ product, save });
            await flushPromises();

            await wrapper.find('.sw-product-modal-delivery__save-button').trigger('click');

            expect(save).toHaveBeenCalledTimes(1);

            return save.mock.calls[0][0];
        }

        it('should save a single variant and disable the properties for listings', async () => {
            const savedProduct = await saveVariantListingConfig({
                listingMode: 'single',
                variantListingConfig: {
                    displayParent: null,
                    mainVariantId: null,
                    configuratorGroupConfig: [createExpandedGroup()],
                },
            });

            expect(savedProduct.listingMode).toBeUndefined();
            expect(savedProduct.variantListingConfig).toEqual({
                displayParent: false,
                mainVariantId: null,
                configuratorGroupConfig: [{ ...createExpandedGroup(), expressionForListings: false }],
            });
        });

        it('should keep the main product when a single product is saved', async () => {
            const savedProduct = await saveVariantListingConfig({
                listingMode: 'single',
                variantListingConfig: {
                    displayParent: true,
                    mainVariantId: 'variant-id',
                    configuratorGroupConfig: null,
                },
            });

            expect(savedProduct.variantListingConfig).toEqual({
                displayParent: true,
                mainVariantId: 'variant-id',
                configuratorGroupConfig: null,
            });
        });

        it('should keep the properties for listings when the expanded variants are saved', async () => {
            const savedProduct = await saveVariantListingConfig({
                listingMode: 'expanded',
                variantListingConfig: {
                    displayParent: false,
                    mainVariantId: 'variant-id',
                    configuratorGroupConfig: [createExpandedGroup()],
                },
            });

            expect(savedProduct.variantListingConfig).toEqual({
                displayParent: null,
                mainVariantId: null,
                configuratorGroupConfig: [createExpandedGroup()],
            });
        });

        it('should keep the configuration when the product listings tab was not opened', async () => {
            const savedProduct = await saveVariantListingConfig({
                variantListingConfig: {
                    displayParent: null,
                    mainVariantId: null,
                    configuratorGroupConfig: [createExpandedGroup()],
                },
            });

            expect(savedProduct.variantListingConfig).toEqual({
                displayParent: null,
                mainVariantId: null,
                configuratorGroupConfig: [createExpandedGroup()],
            });
        });
    });
});
