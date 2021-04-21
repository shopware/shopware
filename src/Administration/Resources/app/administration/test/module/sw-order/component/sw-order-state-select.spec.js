import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-state-select';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-order-state-select'), {
        stubs: {
            'sw-field': true
        },
        propsData: {
            transitionOptions: []
        }
    });
}

describe('src/module/sw-order/component/sw-order-state-select', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an enabled sw-field', async () => {
        const swField = wrapper.find('sw-field-stub');

        expect(swField.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled sw-field', async () => {
        await wrapper.setProps({ disabled: true });
        const swField = wrapper.find('sw-field-stub');

        expect(swField.attributes().disabled).toBe('true');
    });
});
