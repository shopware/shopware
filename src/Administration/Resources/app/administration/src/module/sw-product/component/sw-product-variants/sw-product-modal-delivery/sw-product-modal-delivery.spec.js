/*
 * @package inventory
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-modal-delivery', { sync: true }), {
        props: {
            product: {},
            selectedGroups: [],
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => ({ id: 'id' }),
                        save: () => Promise.resolve({}),
                    }),
                },
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
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
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
        expect(saveButton.classes()).toContain('sw-button--disabled');
    });

    it('should have an enabled save button', async () => {
        global.activeAclRoles = ['product.editor'];
        const wrapper = await createWrapper([
            'product.editor',
        ]);
        await flushPromises();

        const saveButton = wrapper.find('.sw-product-modal-delivery__save-button');

        expect(saveButton.exists()).toBeTruthy();
        expect(saveButton.classes()).not.toContain('sw-button--disabled');
    });

    it('should be able to allow save storefront presentation modal', async () => {
        global.activeAclRoles = ['product.editor'];
        const wrapper = await createWrapper([
            'product.editor',
        ]);
        await flushPromises();
        const saveButton = wrapper.find('.sw-product-modal-delivery__save-button');

        expect(saveButton.exists()).toBeTruthy();
        expect(saveButton.classes()).not.toContain('sw-button--disabled');
        await saveButton.trigger('click');
        const emitted = wrapper.emitted()['configuration-close'];
        expect(emitted).toBeTruthy();
    });
});
