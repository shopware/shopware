/*
 * @package inventory
 */

import { mount } from '@vue/test-utils_v3';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-product-layout-assignment', { sync: true }), {
        global: {
            stubs: {
                'sw-cms-list-item': true,
                'sw-button': true,
            },
            provide: {
                acl: {
                    can: (identifier) => {
                        if (!identifier) { return true; }

                        return privileges.includes(identifier);
                    },
                },
            },
        },
    });
}

describe('module/sw-product/component/sw-product-layout-assignment', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should emit an event when openLayoutModal() function is called', async () => {
        wrapper.vm.openLayoutModal();

        const pageChangeEvents = wrapper.emitted()['modal-layout-open'];
        expect(pageChangeEvents).toHaveLength(1);
    });

    it('should emit an event when openInPageBuilder() function is called', async () => {
        wrapper.vm.openInPageBuilder();

        const pageChangeEvents = wrapper.emitted()['button-edit-click'];
        expect(pageChangeEvents).toHaveLength(1);
    });

    it('should emit an event when onLayoutReset() function is called', async () => {
        wrapper.vm.onLayoutReset();

        const pageChangeEvents = wrapper.emitted()['button-delete-click'];
        expect(pageChangeEvents).toHaveLength(1);
    });

    it('should not be able to edit layout assignment', async () => {
        wrapper = await createWrapper(['product.viewer']);
        await flushPromises();

        const cmsItem = wrapper.find('sw-cms-list-item-stub');
        const buttons = wrapper.findAll('sw-button-stub');

        expect(cmsItem.attributes('disabled')).toBeTruthy();

        buttons.forEach(button => {
            expect(button.attributes('disabled')).toBeTruthy();
        });
    });

    it('should be able to edit layout assignment', async () => {
        wrapper = await createWrapper(['product.editor']);

        const cmsItem = wrapper.find('sw-cms-list-item-stub');
        const buttons = wrapper.findAll('sw-button-stub');

        expect(cmsItem.attributes('disabled')).toBeFalsy();

        buttons.forEach(button => {
            expect(button.attributes('disabled')).toBeFalsy();
        });
    });
});
