import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-ratings/sw-extension-rating-stars';

describe('src/module/sw-extension/component/sw-extension-rating-stars', () => {
    /** @type Wrapper */
    let wrapper;

    function createWrapper() {
        return shallowMount(Shopware.Component.build('sw-extension-rating-stars'), {
            stubs: {
                'sw-icon': true
            }
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it.each([0, 1, 2, 3, 4, 5])('should show %d yellow star(s)', async rating => {
        wrapper = createWrapper();
        await wrapper.setProps({ rating });

        const amountOfFullStars = wrapper.findAll('.sw-extension-rating-stars__star--is-rated').length;
        expect(amountOfFullStars).toBe(rating);
    });

    it.each([0.5, 1.5, 2.5, 3.5, 4.5])('should show %d yellow star(s)', async rating => {
        wrapper = createWrapper();

        await wrapper.setProps({ rating });
        const amountOfFullStars = wrapper.findAll('.sw-extension-rating-stars__star--is-rated').length;
        const amountOfPartialStars = wrapper.findAll('.sw-extension-rating-stars__partial-star').length;

        expect(amountOfPartialStars).toBe(1);
        expect(amountOfFullStars).toBe(rating - 0.5);
    });
});
