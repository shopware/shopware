import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-modal';
import 'src/app/component/base/sw-button';

let productData = [];

function setProductData(products) {
    productData = [...products];
}

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
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve(productData)
                    };
                }
            }
        },
        propsData: {
            salesChannel: {
                id: 1,
                name: 'Headless'
            }
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

        const products = [
            {
                name: 'Test product 1',
                id: '1'
            }
        ];

        setProductData(products);

        wrapper.find('.sw-button--primary').trigger('click');

        expect(wrapper.emitted('products-add')).toBeTruthy();
        expect(wrapper.emitted('products-add')[0]).toEqual([wrapper.vm.products]);
    });
});
