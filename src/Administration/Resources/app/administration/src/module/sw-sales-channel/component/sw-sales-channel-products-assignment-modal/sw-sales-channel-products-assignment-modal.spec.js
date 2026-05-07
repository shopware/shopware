/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/base/sw-button';

async function createWrapper(activeTab = 'singleProducts') {
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
                        data() {
                            return { active: activeTab };
                        },
                        template: '<div><slot></slot><slot name="content" v-bind="{ active }"></slot></div>',
                    },
                    'sw-tabs-item': true,
                    'mt-tabs': {
                        props: {
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: false,
                                default: '',
                            },
                            defaultItem: {
                                type: String,
                                required: false,
                                default: '',
                            },
                        },
                        emits: ['new-item-active'],
                        template: '<div class="mt-tabs-stub"></div>',
                    },
                    'sw-loader': true,
                    'router-link': true,
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
    beforeEach(() => {
        global.activeFeatureFlags = [];
    });

    it('should emit modal close event', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('.sw-sales-channel-products-assignment-modal__close-button').trigger('click');

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should emit products data when clicking Add Products button to assign product individually', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            singleProducts: [
                {
                    id: '1',
                    name: 'Test product',
                },
            ],
        });

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
        await wrapper.setData({
            categoryProducts: products,
        });

        const assignButton = wrapper.findByText('button', 'sw-sales-channel.detail.products.buttonAddProducts');
        await assignButton.trigger('click');

        expect(wrapper.emitted('products-add')).toBeTruthy();
        expect(wrapper.emitted('products-add')[0]).toEqual([products]);
    });

    it('should remove duplicated products before emitting', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            singleProducts: [
                {
                    name: 'Test product 1',
                    id: '1',
                },
                {
                    name: 'Test product 2',
                    id: '2',
                },
            ],
            groupProducts: [
                {
                    name: 'Test product 2',
                    id: '2',
                },
                {
                    name: 'Test product 3',
                    id: '3',
                },
            ],
        });

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

    it('should render Meteor tabs when the feature is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const mtTabs = wrapper.getComponent('.mt-tabs-stub');

        expect(mtTabs.props('positionIdentifier')).toBe('sw-sales-channel-products-assignment-modal');
        expect(mtTabs.props('defaultItem')).toBe('singleProducts');
        expect(mtTabs.props('items')).toEqual([
            expect.objectContaining({
                label: 'sw-sales-channel.detail.productAssignmentModal.singleProducts',
                name: 'singleProducts',
            }),
            expect.objectContaining({
                label: 'sw-sales-channel.detail.productAssignmentModal.categories.title',
                name: 'categories',
            }),
            expect.objectContaining({
                label: 'sw-sales-channel.detail.productAssignmentModal.dynamicProductGroups.title',
                name: 'dynamicProductGroups',
            }),
        ]);

        mtTabs.vm.$emit('new-item-active', 'dynamicProductGroups');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('dynamicProductGroups');
    });
});
