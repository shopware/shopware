/**
 * @package buyers-experience
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
                    'sw-button': await wrapTestComponent('sw-button', {
                        sync: true,
                    }),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
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
                    'sw-icon': true,
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

        await wrapper.find('.sw-button--primary').trigger('click');

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

        const assignButton = wrapper.find('.sw-button--primary');
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
});
