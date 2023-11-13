/*
 * @package inventory
 */

import { mount } from '@vue/test-utils_v3';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-modal-delivery', { sync: true }), {
        props: {
            product: {},
            selectedGroups: [],
        },
        global: {
            provide: {
                repositoryFactory: {},
                shortcutService: {
                    startEventListener: () => {},
                    stopEventListener: () => {},
                },
            },
            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-tabs': true,
                'sw-tabs-item': true,
                'sw-product-variants-delivery-order': true,
                'sw-button': true,
                'sw-icon': true,
            },
        },
    });
}

describe('src/module/sw-product/component/sw-product-variants/sw-product-modal-delivery', () => {
    it('should be a Vue.JS component', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled save button', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        const saveButton = wrapper.find('.sw-product-modal-delivery__save-button');

        expect(saveButton.exists()).toBeTruthy();
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should have an enabled save button', async () => {
        global.activeAclRoles = ['product.editor'];
        const wrapper = await createWrapper([
            'product.editor',
        ]);
        await flushPromises();

        const saveButton = wrapper.find('.sw-product-modal-delivery__save-button');

        expect(saveButton.exists()).toBeTruthy();
        expect(saveButton.attributes().disabled).toBeFalsy();
    });
});
