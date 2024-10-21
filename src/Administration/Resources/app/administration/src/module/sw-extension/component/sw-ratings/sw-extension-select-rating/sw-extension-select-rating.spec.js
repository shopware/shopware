import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-select-rating', () => {
    async function createWrapper() {
        return mount(
            await wrapTestComponent('sw-extension-select-rating', {
                sync: true,
            }),
            {
                global: {
                    provide: {
                        validationService: {},
                    },
                    stubs: {
                        'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                        'sw-field-error': await wrapTestComponent('sw-field-error', { sync: true }),
                        'sw-extension-rating-stars': await wrapTestComponent('sw-extension-rating-stars', { sync: true }),
                        'sw-icon': true,
                        'sw-inheritance-switch': true,
                        'sw-ai-copilot-badge': true,
                        'sw-help-text': true,
                    },
                },
            },
        );
    }

    it.each([
        [
            0,
            5,
        ],
        [
            1,
            4,
        ],
        [
            2,
            3,
        ],
        [
            3,
            2,
        ],
        [
            4,
            1,
        ],
    ])('should have %d yellow star(s)', async (starPosition, expectedValue) => {
        const wrapper = await createWrapper();

        const buttons = wrapper.findAll('button.sw-extension-rating-stars__star');
        const toBeClickedButton = buttons.at(starPosition);

        await toBeClickedButton.trigger('click');

        const amountOfRatedStars = wrapper.findAll('.sw-extension-rating-stars__star--is-rated').length;

        expect(amountOfRatedStars).toBe(expectedValue);
    });
});
