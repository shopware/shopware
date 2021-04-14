import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/component/sw-product-layout-assignment';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-product-layout-assignment'), {
        stubs: {
            'sw-cms-list-item': true,
            'sw-button': true
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        }
    });
}

describe('module/sw-product/component/sw-product-layout-assignment', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should emit an event when openLayoutModal() function is called', () => {
        wrapper.vm.openLayoutModal();

        const pageChangeEvents = wrapper.emitted()['modal-layout-open'];
        expect(pageChangeEvents.length).toBe(1);
    });

    it('should emit an event when openInPageBuilder() function is called', () => {
        wrapper.vm.openInPageBuilder();

        const pageChangeEvents = wrapper.emitted()['button-edit-click'];
        expect(pageChangeEvents.length).toBe(1);
    });

    it('should emit an event when onLayoutReset() function is called', () => {
        wrapper.vm.onLayoutReset();

        const pageChangeEvents = wrapper.emitted()['button-delete-click'];
        expect(pageChangeEvents.length).toBe(1);
    });

    it('should not be able to edit layout assignment', () => {
        wrapper = createWrapper(['product.viewer']);

        const cmsItem = wrapper.find('sw-cms-list-item-stub');
        const buttons = wrapper.findAll('sw-button-stub');

        expect(cmsItem.attributes('disabled')).toBeTruthy();

        buttons.wrappers.forEach(button => {
            expect(button.attributes('disabled')).toBeTruthy();
        });
    });

    it('should be able to edit layout assignment', () => {
        wrapper = createWrapper(['product.editor']);

        const cmsItem = wrapper.find('sw-cms-list-item-stub');
        const buttons = wrapper.findAll('sw-button-stub');

        expect(cmsItem.attributes('disabled')).toBeFalsy();

        buttons.wrappers.forEach(button => {
            expect(button.attributes('disabled')).toBeFalsy();
        });
    });
});
