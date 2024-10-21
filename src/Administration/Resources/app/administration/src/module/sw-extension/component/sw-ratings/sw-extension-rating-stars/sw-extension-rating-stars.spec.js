import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-rating-stars', () => {
    async function createWrapper() {
        return mount(
            await wrapTestComponent('sw-extension-rating-stars', {
                sync: true,
            }),
            {
                global: {
                    stubs: {
                        'sw-icon': true,
                    },
                },
            },
        );
    }

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it.each([
        0,
        1,
        2,
        3,
        4,
        5,
    ])('should show %d yellow star(s)', async (rating) => {
        const wrapper = await createWrapper();
        await wrapper.setProps({ rating });

        const amountOfFullStars = wrapper.findAll('.sw-extension-rating-stars__star--is-rated').length;
        expect(amountOfFullStars).toBe(rating);
    });

    it.each([
        0.5,
        1.5,
        2.5,
        3.5,
        4.5,
    ])('should show %d yellow star(s)', async (rating) => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ rating });
        const amountOfFullStars = wrapper.findAll('.sw-extension-rating-stars__star--is-rated').length;
        const amountOfPartialStars = wrapper.findAll('.sw-extension-rating-stars__partial-star').length;

        expect(amountOfPartialStars).toBe(1);
        expect(amountOfFullStars).toBe(rating - 0.5);
    });
});
