/**
 * @sw-package discovery
 */

import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import 'src/app/component/base/sw-button';

async function createWrapper(activeTab = 'singleProducts', featureActive = false) {
    return mount(
        await wrapTestComponent('sw-sales-channel-products-assignment-modal', {
            sync: true,
        }),
        {
            global: {
                directives: {
                    hide: {},
                },
                stubs: {
                    'sw-sales-channel-products-assignment-single-products': true,
                    'sw-sales-channel-product-assignment-categories': true,
                    'sw-sales-channel-products-assignment-dynamic-product-groups': true,
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-modal': {
                        template:
                            '<div class="sw-modal"><slot></slot><slot name="content"></slot><slot name="modal-footer"></slot></div>',
                    },
                    'sw-tabs': {
                        name: 'sw-tabs',
                        data() {
                            return { active: activeTab };
                        },
                        template:
                            '<div class="sw-tabs"><slot v-bind="{ active }"></slot><slot name="content" v-bind="{ active }"></slot></div>',
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
                        },
                        template: '<div class="mt-tabs"></div>',
                    },
                    'sw-tabs-item': true,
                    'sw-loader': true,
                    'router-link': true,
                },
                provide: {
                    feature: {
                        isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                    },
                },
            },
            props: {
                salesChannel: {
                    id: 1,
                    name: 'Headless',
                },
                isAssignProductLoading: false,
            },
        },
    );
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-modal', () => {
    it('should render deprecated tabs when the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-tabs').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper('singleProducts', true);
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-sales-channel-products-assignment-modal');
        expect(tabs.props('defaultItem')).toBe('singleProducts');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-sales-channel.detail.productAssignmentModal.singleProducts',
                name: 'singleProducts',
            },
            {
                label: 'sw-sales-channel.detail.productAssignmentModal.categories.title',
                name: 'categories',
            },
            {
                label: 'sw-sales-channel.detail.productAssignmentModal.dynamicProductGroups.title',
                name: 'dynamicProductGroups',
            },
        ]);
        expect(wrapper.find('.sw-tabs').exists()).toBe(false);
    });

    it('should update the active tab when meteor tabs emit a new active item', async () => {
        const wrapper = await createWrapper('singleProducts', true);

        await wrapper.getComponent({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'categories');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('categories');
    });

    it('should emit modal close event', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('.sw-sales-channel-products-assignment-modal__close-button').trigger('click');

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should emit products data when clicking Add Products button to assign product individually', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.singleProducts = [
            {
                id: '1',
                name: 'Test product',
            },
        ];
        await nextTick();

        await wrapper.findByText('button', 'sw-sales-channel.detail.products.buttonAddProducts').trigger('click');

        expect(wrapper.emitted('products-add')).toBeTruthy();
        expect(wrapper.emitted('products-add')[0]).toEqual([
            wrapper.vm.products,
        ]);
    });

    it('should emit products data when clicking Add Products button to assign product by categories', async () => {
        const products = [
            {
                name: 'Test product 1',
                id: '1',
            },
            {
                name: 'Test product 2',
                id: '2',
            },
        ];

        const wrapper = await createWrapper();
        wrapper.vm.categoryProducts = products;
        await nextTick();

        const assignButton = wrapper.findByText('button', 'sw-sales-channel.detail.products.buttonAddProducts');
        await assignButton.trigger('click');

        expect(wrapper.emitted('products-add')).toBeTruthy();
        expect(wrapper.emitted('products-add')[0]).toEqual([products]);
    });

    it('should remove duplicated products before emitting', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.singleProducts = [
            {
                name: 'Test product 1',
                id: '1',
            },
            {
                name: 'Test product 2',
                id: '2',
            },
        ];
        wrapper.vm.groupProducts = [
            {
                name: 'Test product 2',
                id: '2',
            },
            {
                name: 'Test product 3',
                id: '3',
            },
        ];
        await nextTick();

        expect(wrapper.vm.products).toEqual([
            {
                name: 'Test product 1',
                id: '1',
            },
            {
                name: 'Test product 2',
                id: '2',
            },
            {
                name: 'Test product 3',
                id: '3',
            },
        ]);
        expect(wrapper.vm.productCount).toBe(3);
    });

    it('should update the corresponding product successfully', async () => {
        const wrapper = await createWrapper();
        const groupProductsMock = [
            {
                id: 1,
                name: 'Low prices',
            },
            {
                id: 2,
                name: 'Standard prices',
            },
            {
                id: 3,
                name: 'High prices',
            },
        ];

        wrapper.vm.onChangeSelection(groupProductsMock, 'groupProducts');

        expect(wrapper.vm.groupProducts).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ name: 'Low prices' }),
                expect.objectContaining({ name: 'Standard prices' }),
                expect.objectContaining({ name: 'High prices' }),
            ]),
        );
    });

    it('should update the product loading state correctly', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.setProductLoading(true);

        expect(wrapper.vm.isProductLoading).toBe(true);
    });
});
