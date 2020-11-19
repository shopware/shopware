import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/component/sw-product-layout-assignment';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-product-layout-assignment'), {
        mocks: {
            $t: key => key,
            $tc: key => key
        },
        stubs: {
            'sw-cms-list-item': true,
            'sw-button': true
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
});
