import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-code-editor';

function createWrapper(options = {}) {
    return shallowMount(Shopware.Component.build('sw-code-editor'), options);
}

describe('components/form/sw-code-editor', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be read only when enabled', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.aceConfig.readOnly).toBe(false);
    });

    it('should be read only when disabled', async () => {
        const wrapper = createWrapper({
            propsData: {
                disabled: true
            }
        });

        expect(wrapper.vm.aceConfig.readOnly).toBe(true);
    });
});
