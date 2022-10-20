import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-arrow-field';

function createWrapper(customOptions = {}) {
    return shallowMount(Shopware.Component.build('sw-arrow-field'), { ...customOptions });
}


describe('src/app/component/rule/sw-arrow-field', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have enabled links', async () => {
        const wrapper = createWrapper();

        expect(wrapper.classes()).not.toContain('is--disabled');
    });

    it('should have disabled links', async () => {
        const wrapper = createWrapper({
            propsData: {
                disabled: true
            }
        });

        expect(wrapper.classes()).toContain('is--disabled');
    });
});
