import { shallowMount } from '@vue/test-utils';
import swButton from 'src/app/component/base/sw-button';

describe('components/base/sw-button', () => {
    it('should be a Vue.js component', () => {
        const wrapper = shallowMount(swButton);
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should render a plain button', () => {
        const label = 'Button text';
        const wrapper = shallowMount(swButton, {
            slots: {
                default: label
            }
        });
        const slot = wrapper.find('.sw-button__content');
        expect(slot).toBeTruthy();
        expect(slot.text()).toBe(label);
    });
});
