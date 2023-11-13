import { shallowMount } from '@vue/test-utils';
import swExtensionReviewCreation from 'src/module/sw-extension/component/sw-ratings/sw-extension-review-creation';
import swExtensionReviewCreationInputs from 'src/module/sw-extension/component/sw-ratings/sw-extension-review-creation-inputs';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-button-process';
import 'src/app/component/form/sw-gtc-checkbox';
import swExtensionSelectRating from 'src/module/sw-extension/component/sw-ratings/sw-extension-select-rating';
import swExtensionRatingStars from 'src/module/sw-extension/component/sw-ratings/sw-extension-rating-stars';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/utils/sw-external-link';

Shopware.Component.register('sw-extension-review-creation', swExtensionReviewCreation);
Shopware.Component.register('sw-extension-review-creation-inputs', swExtensionReviewCreationInputs);
Shopware.Component.extend('sw-extension-select-rating', 'sw-text-field', swExtensionSelectRating);
Shopware.Component.register('sw-extension-rating-stars', swExtensionRatingStars);

/**
 * @package services-settings
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-review-creation', () => {
    /** @type Wrapper */
    let wrapper;

    async function createWrapper() {
        return shallowMount(await Shopware.Component.build('sw-extension-review-creation'), {
            propsData: {
                extension: {},
            },
            provide: {
                validationService: {},
                extensionStoreActionService: {
                    rateExtension: jest.fn(),
                },
            },
            computed: {
                installedVersion() {
                    return '1.0.0';
                },
            },
            stubs: {
                'sw-extension-review-creation-inputs': await Shopware.Component.build('sw-extension-review-creation-inputs'),
                'sw-text-field': await Shopware.Component.build('sw-text-field'),
                'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-field-error': await Shopware.Component.build('sw-field-error'),
                'sw-extension-select-rating': await Shopware.Component.build('sw-extension-select-rating'),
                'sw-extension-rating-stars': await Shopware.Component.build('sw-extension-rating-stars'),
                'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
                'sw-icon': true,
                'sw-textarea-field': {
                    template: '<textarea></textarea>',
                },
                'sw-gtc-checkbox': await Shopware.Component.build('sw-gtc-checkbox'),
                'sw-button': await Shopware.Component.build('sw-button'),
                'sw-button-process': await Shopware.Component.build('sw-button-process'),
                'sw-external-link': await Shopware.Component.build('sw-external-link'),
                'sw-loader': true,
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

    it('should enable the button when the gtc are accepted', async () => {
        wrapper = await createWrapper();

        const submitButton = wrapper.find('.sw-button.sw-button--primary');
        expect(submitButton.attributes('disabled')).toBe('disabled');

        const gtcCheckbox = wrapper.find('input[type="checkbox"]');

        await gtcCheckbox.setChecked();
        expect(gtcCheckbox.element.checked).toBe(true);

        expect(submitButton.attributes('disabled')).toBeUndefined();
    });

    it('should make an api request', async () => {
        wrapper = await createWrapper();
        const gtcCheckbox = wrapper.find('input[type="checkbox"]');

        await gtcCheckbox.setChecked();
        expect(gtcCheckbox.element.checked).toBe(true);

        // input components
        const descriptionTextarea = wrapper.find('textarea');
        const titleInput = wrapper.find('input[type="text"]');
        const star = wrapper.find('button.sw-extension-rating-stars__star');

        // creating review
        await titleInput.setValue('bad app');
        await descriptionTextarea.setValue('not very good');
        await star.trigger('click');

        // submitting review
        const submitButton = wrapper.find('.sw-button.sw-button--primary');
        await submitButton.trigger('click');

        expect(wrapper.vm.extensionStoreActionService.rateExtension).toHaveBeenCalled();
    });
});
