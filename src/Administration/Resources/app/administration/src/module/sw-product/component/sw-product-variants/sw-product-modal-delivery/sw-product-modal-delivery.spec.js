import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/component/sw-product-variants/sw-product-modal-delivery';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-product-modal-delivery'), {
        propsData: {
            product: {},
            selectedGroups: []
        },
        provide: {
            repositoryFactory: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        },
        stubs: {
            'sw-modal': true,
            'sw-tabs': true,
            'sw-tabs-item': true,
            'sw-product-variants-delivery-order': true,
            'sw-button': true
        }
    });
}

describe('src/module/sw-product/component/sw-product-variants/sw-product-modal-delivery', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled save button', async () => {
        const wrapper = createWrapper();
        const saveButton = wrapper.find('.sw-product-modal-delivery__save-button');

        expect(saveButton.exists()).toBeTruthy();
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should have an enabled save button', async () => {
        const wrapper = createWrapper([
            'product.editor'
        ]);
        const saveButton = wrapper.find('.sw-product-modal-delivery__save-button');

        expect(saveButton.exists()).toBeTruthy();
        expect(saveButton.attributes().disabled).toBeFalsy();
    });
});
