import { mount } from '@vue/test-utils';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-button';
import 'src/module/sw-cms/elements/product-listing/config/components/sw-cms-el-config-product-listing-config-delete-modal';

function createWrapper() {
    return mount({
        template: `
            <div>
<sw-cms-el-config-product-listing-config-delete-modal @cancel="callCancel"
                                                      @confirm="callConfirm"
                                                      :productSorting="{}">
</sw-cms-el-config-product-listing-config-delete-modal>
            </div>`,
        methods: {
            callCancel: jest.fn(),
            callConfirm: jest.fn()
        },
        components: {
            // eslint-disable-next-line max-len
            'sw-cms-el-config-product-listing-config-delete-modal': Shopware.Component.build('sw-cms-el-config-product-listing-config-delete-modal')
        }
    }, {
        stubs: {
            'sw-modal': {
                template: '<div class="sw-modal"><slot name="modal-footer">Test</slot></div>'
            },
            'sw-button': Shopware.Component.build('sw-button')
        }
    });
}

// eslint-disable-next-line max-len
describe('src/module/sw-cms/elements/product-listing/config/components/sw-cms-el-config-product-listing-config-delete-modal', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('cancels the dialog', async () => {
        const wrapper = createWrapper();
        const modal = wrapper.find('.sw-modal');

        expect(modal.emitted().cancel).not.toBeDefined();

        await wrapper.findAll('button').at(0).trigger('click');

        expect(modal.emitted().cancel).toBeDefined();
    });

    it('confirms the dialog', async () => {
        const wrapper = createWrapper();
        const modal = wrapper.find('.sw-modal');

        expect(modal.emitted().confirm).not.toBeDefined();

        await wrapper.findAll('button').at(1).trigger('click');

        expect(modal.emitted().confirm).toBeDefined();
    });
});
