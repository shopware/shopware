import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-modal';
import 'src/app/component/base/sw-button';

function createWrapper(activeTab = 'singleProducts') {
    return shallowMount(Shopware.Component.build('sw-sales-channel-products-assignment-modal'), {
        stubs: {
            'sw-sales-channel-products-assignment-single-products': true,
            'sw-sales-channel-product-assignment-categories': true,
            'sw-sales-channel-products-assignment-dynamic-product-groups': true,
            'sw-container': true,
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-modal': true,
            'sw-tabs': {
                data() {
                    return { active: activeTab };
                },
                template: '<div><slot></slot><slot name="content" v-bind="{ active }"></slot></div>'
            },
            'sw-tabs-item': true,
            'sw-icon': true
        },
        propsData: {
            salesChannel: {
                id: 1,
                name: 'Headless'
            },
            isAssignProductLoading: false
        }
    });
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-modal', () => {
    it('should emit modal close event', async () => {
        const wrapper = createWrapper();

        wrapper.findAll('.sw-button').at(0).trigger('click');

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should emit products data when clicking Add Products button to assign product individually', async () => {
        const wrapper = createWrapper();
        await wrapper.setData({
            singleProducts: [
                {
                    id: '1',
                    name: 'Test product'
                }
            ]
        });

        wrapper.find('.sw-button--primary').trigger('click');

        expect(wrapper.emitted('products-add')).toBeTruthy();
        expect(wrapper.emitted('products-add')[0]).toEqual([wrapper.vm.products]);
    });

    it('should emit products data when clicking Add Products button to assign product by categories', async () => {
        const products = [
            {
                name: 'Test product 1',
                id: '1'
            },
            {
                name: 'Test product 2',
                id: '2'
            }
        ];

        const wrapper = createWrapper();
        await wrapper.setData({
            categoryProducts: products
        });

        const assignButton = wrapper.find('.sw-button--primary');
        await assignButton.trigger('click');

        expect(wrapper.emitted('products-add')).toBeTruthy();
        expect(wrapper.emitted('products-add')[0]).toEqual([products]);
    });

    it('should remove duplicated products before emitting', async () => {
        const wrapper = createWrapper();
        await wrapper.setData({
            singleProducts: [
                {
                    name: 'Test product 1',
                    id: '1'
                },
                {
                    name: 'Test product 2',
                    id: '2'
                }
            ],
            groupProducts: [
                {
                    name: 'Test product 2',
                    id: '2'
                },
                {
                    name: 'Test product 3',
                    id: '3'
                }
            ]
        });

        expect(wrapper.vm.products).toEqual([
            {
                name: 'Test product 1',
                id: '1'
            },
            {
                name: 'Test product 2',
                id: '2'
            },
            {
                name: 'Test product 3',
                id: '3'
            }
        ]);
        expect(wrapper.vm.productCount).toBe(3);
    });

    it('should update the corresponding product successfully', () => {
        const wrapper = createWrapper();
        const groupProductsMock = [
            {
                id: 1,
                name: 'Low prices'
            },
            {
                id: 2,
                name: 'Standard prices'
            },
            {
                id: 3,
                name: 'High prices'
            }
        ];

        wrapper.vm.onChangeSelection(groupProductsMock, 'groupProducts');

        expect(wrapper.vm.groupProducts).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ name: 'Low prices' }),
                expect.objectContaining({ name: 'Standard prices' }),
                expect.objectContaining({ name: 'High prices' })
            ])
        );
    });

    it('should update the product loading state correctly', () => {
        const wrapper = createWrapper();

        wrapper.vm.setProductLoading(true);

        expect(wrapper.vm.isProductLoading).toBe(true);
    });
});
