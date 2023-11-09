import { shallowMount } from '@vue/test-utils';
import swExtensionSelectRating from 'src/module/sw-extension/component/sw-ratings/sw-extension-select-rating';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import swExtensionRatingStars from 'src/module/sw-extension/component/sw-ratings/sw-extension-rating-stars';

Shopware.Component.extend('sw-extension-select-rating', 'sw-text-field', swExtensionSelectRating);
Shopware.Component.register('sw-extension-rating-stars', swExtensionRatingStars);

/**
 * @package services-settings
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-select-rating', () => {
    /** @type Wrapper */
    let wrapper;

    async function createWrapper() {
        return shallowMount(await Shopware.Component.build('sw-extension-select-rating'), {
            provide: {
                validationService: {},
            },
            stubs: {
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-field-error': await Shopware.Component.build('sw-field-error'),
                'sw-extension-rating-stars': await Shopware.Component.build('sw-extension-rating-stars'),
                'sw-icon': true,
            },
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it.each([
        [0, 5],
        [1, 4],
        [2, 3],
        [3, 2],
        [4, 1],
    ])('should have %d yellow star(s)', async (starPosition, expectedValue) => {
        wrapper = await createWrapper();

        const buttons = wrapper.findAll('button.sw-extension-rating-stars__star');
        const toBeClickedButton = buttons.at(starPosition);

        await toBeClickedButton.trigger('click');

        const amountOfRatedStars = wrapper.findAll('.sw-extension-rating-stars__star--is-rated').length;

        expect(amountOfRatedStars).toBe(expectedValue);
    });
});
