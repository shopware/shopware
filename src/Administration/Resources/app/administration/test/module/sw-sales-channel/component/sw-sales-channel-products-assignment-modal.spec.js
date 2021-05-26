import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-modal';
import 'src/app/component/base/sw-button';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-sales-channel-products-assignment-modal'), {
        stubs: {
            'sw-sales-channel-products-assignment-single-products': true,
            'sw-container': true,
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-modal': true,
            'sw-tabs': true,
            'sw-tab-items': true
        },
        provide: {},
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

    it('should emit products data when clicking Add Products button', async () => {
        const wrapper = createWrapper();
        wrapper.setData({
            products: {
                1: {
                    id: 1,
                    name: 'Test product'
                }
            }
        });

        wrapper.find('.sw-button--primary').trigger('click');

        expect(wrapper.emitted('products-add')).toBeTruthy();
        expect(wrapper.emitted('products-add')[0]).toEqual([wrapper.vm.products]);
    });
});
