/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import swCmsElConfigProductListingConfigDeleteModal from 'src/module/sw-cms/elements/product-listing/config/components/sw-cms-el-config-product-listing-config-delete-modal';

Shopware.Component.register('sw-cms-el-config-product-listing-config-delete-modal', swCmsElConfigProductListingConfigDeleteModal);
async function createWrapper() {
    // Not found in componentregistry
    return mount(await Shopware.Component.build('sw-cms-el-config-product-listing-config-delete-modal'), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal"><slot name="modal-footer">Test</slot></div>',
                },
                'sw-button': {
                    template: '<div class="sw-button"></div>',
                },
            },
        },
        props: {
            productSorting: {},
        },
    });
}

// eslint-disable-next-line max-len
describe('src/module/sw-cms/elements/product-listing/config/components/sw-cms-el-config-product-listing-config-delete-modal', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('cancels the dialog', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.emitted('confirm')).toBeUndefined();
        expect(wrapper.emitted('cancel')).toBeUndefined();

        wrapper.findComponent('.sw-cms-el-config-product-listing-config-delete-modal__cancel').vm.$emit('click');

        expect(wrapper.emitted('confirm')).toBeUndefined();
        expect(wrapper.emitted('cancel')).toStrictEqual([[]]);
    });

    it('confirms the dialog', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.emitted('confirm')).toBeUndefined();
        expect(wrapper.emitted('cancel')).toBeUndefined();


        wrapper.findComponent('.sw-cms-el-config-product-listing-config-delete-modal__confirm').vm.$emit('click');

        expect(wrapper.emitted('confirm')).toStrictEqual([[]]);
        expect(wrapper.emitted('cancel')).toBeUndefined();
    });
});
