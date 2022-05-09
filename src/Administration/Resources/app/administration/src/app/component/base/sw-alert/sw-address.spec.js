import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-address';

function createWrapper(propsData = {}) {
    return shallowMount(Shopware.Component.build('sw-address'), {
        propsData
    });
}


describe('components/base/sw-address', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the formatting address', async () => {
        const wrapper = createWrapper({
            formattingAddress: 'some-text',
        });

        const formattingAddress = wrapper.find('.sw-address__formatting');
        expect(formattingAddress).toBeTruthy();
        expect(formattingAddress.text()).toBe('some-text');
    });
});
