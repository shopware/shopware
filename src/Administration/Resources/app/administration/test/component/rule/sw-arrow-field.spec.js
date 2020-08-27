import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-arrow-field';

function createWrapper(customOptions = {}) {
    return shallowMount(Shopware.Component.build('sw-arrow-field'), { ...customOptions });
}


describe('src/app/component/rule/sw-arrow-field', () => {
    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();
        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should have enabled links', () => {
        const wrapper = createWrapper();

        expect(wrapper.classes()).not.toContain('is--disabled');
    });

    it('should have disabled links', () => {
        const wrapper = createWrapper({
            propsData: {
                disabled: true
            }
        });

        expect(wrapper.classes()).toContain('is--disabled');
    });
});
