import { shallowMount } from '@vue/test-utils';
import swExtensionRatingStars from 'src/module/sw-extension/component/sw-ratings/sw-extension-rating-stars';

Shopware.Component.register('sw-extension-rating-stars', swExtensionRatingStars);

/**
 * @package merchant-services
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-rating-stars', () => {
    /** @type Wrapper */
    let wrapper;

    async function createWrapper() {
        return shallowMount(await Shopware.Component.build('sw-extension-rating-stars'), {
            stubs: {
                'sw-icon': true
            }
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it.each([0, 1, 2, 3, 4, 5])('should show %d yellow star(s)', async rating => {
        wrapper = await createWrapper();
        await wrapper.setProps({ rating });

        const amountOfFullStars = wrapper.findAll('.sw-extension-rating-stars__star--is-rated').length;
        expect(amountOfFullStars).toBe(rating);
    });

    it.each([0.5, 1.5, 2.5, 3.5, 4.5])('should show %d yellow star(s)', async rating => {
        wrapper = await createWrapper();

        await wrapper.setProps({ rating });
        const amountOfFullStars = wrapper.findAll('.sw-extension-rating-stars__star--is-rated').length;
        const amountOfPartialStars = wrapper.findAll('.sw-extension-rating-stars__partial-star').length;

        expect(amountOfPartialStars).toBe(1);
        expect(amountOfFullStars).toBe(rating - 0.5);
    });
});
