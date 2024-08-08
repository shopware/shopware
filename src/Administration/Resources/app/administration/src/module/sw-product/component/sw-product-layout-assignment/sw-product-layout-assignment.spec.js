/*
 * @package inventory
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-layout-assignment', { sync: true }), {
        global: {
            stubs: {
                'sw-cms-list-item': true,
                'sw-button': true,
                'sw-icon': true,
            },
        },
    });
}

describe('module/sw-product/component/sw-product-layout-assignment', () => {
    let wrapper;

    it('should emit an event when openLayoutModal() function is called', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.openLayoutModal();

        const pageChangeEvents = wrapper.emitted()['modal-layout-open'];
        expect(pageChangeEvents).toHaveLength(1);
    });

    it('should emit an event when openInPageBuilder() function is called', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.openInPageBuilder();

        const pageChangeEvents = wrapper.emitted()['button-edit-click'];
        expect(pageChangeEvents).toHaveLength(1);
    });

    it('should emit an event when onLayoutReset() function is called', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.onLayoutReset();

        const pageChangeEvents = wrapper.emitted()['button-delete-click'];
        expect(pageChangeEvents).toHaveLength(1);
    });

    it('should not be able to edit layout assignment', async () => {
        global.activeAclRoles = ['product.viewer'];
        wrapper = await createWrapper();
        await flushPromises();

        const cmsItem = wrapper.find('sw-cms-list-item-stub');
        const buttons = wrapper.findAll('sw-button-stub');

        expect(cmsItem.attributes('disabled')).toBeTruthy();

        buttons.forEach(button => {
            expect(button.attributes('disabled')).toBeTruthy();
        });
    });

    it('should be able to edit layout assignment', async () => {
        global.activeAclRoles = ['product.editor'];
        wrapper = await createWrapper();

        const cmsItem = wrapper.find('sw-cms-list-item-stub');
        const buttons = wrapper.findAll('sw-button-stub');

        expect(cmsItem.attributes('disabled')).toBeFalsy();

        buttons.forEach(button => {
            expect(button.attributes('disabled')).toBeFalsy();
        });
    });
});
