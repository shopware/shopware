import { shallowMount } from '@vue/test-utils';
import swExtensionReviewCreationInputs from 'src/module/sw-extension/component/sw-ratings/sw-extension-review-creation-inputs';
import swExtensionSelectRating from 'src/module/sw-extension/component/sw-ratings/sw-extension-select-rating';
import swExtensionRatingStars from 'src/module/sw-extension/component/sw-ratings/sw-extension-rating-stars';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';

Shopware.Component.register('sw-extension-review-creation-inputs', swExtensionReviewCreationInputs);
Shopware.Component.extend('sw-extension-select-rating', 'sw-text-field', swExtensionSelectRating);
Shopware.Component.register('sw-extension-rating-stars', swExtensionRatingStars);

/**
 * @package services-settings
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-review-creation-inputs', () => {
    /** @type Wrapper */
    let wrapper;

    async function createWrapper(headlineError, ratingError) {
        return shallowMount(await Shopware.Component.build('sw-extension-review-creation-inputs'), {
            propsData: {
                errors: {
                    headlineError: headlineError || null,
                    ratingError: ratingError || null,
                },
            },
            provide: {
                validationService: {},
            },
            stubs: {
                'sw-text-field': await Shopware.Component.build('sw-text-field'),
                'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-field-error': await Shopware.Component.build('sw-field-error'),
                'sw-extension-select-rating': await Shopware.Component.build('sw-extension-select-rating'),
                'sw-extension-rating-stars': await Shopware.Component.build('sw-extension-rating-stars'),
                'sw-icon': true,
                'sw-textarea-field': {
                    template: '<textarea></textarea>',
                },
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

    it('headline input field should be required', async () => {
        wrapper = await createWrapper();
        const headlineLabel = wrapper.find('label[for="sw-field--headline"]');

        expect(headlineLabel.attributes('class')).toBe('is--required');
    });

    it('rating input field should be required', async () => {
        wrapper = await createWrapper();
        const headlineLabel = wrapper.find('.sw-field__label label');

        expect(headlineLabel.attributes('class')).toBe('is--required');
    });

    it('should display errors on headline input field', async () => {
        const headlineError = {
            _id: '47119c68c9284bb29c8657718b759dd9',
            _code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            _parameters: '__vue_devtool_undefined__',
            _status: '',
            _detail: '',
        };

        wrapper = await createWrapper(headlineError);
        const headlineInput = wrapper.find('.sw-field');

        expect(headlineInput.attributes('class')).toContain('has--error');
    });

    it('should display errors on rating field', async () => {
        const ratingError = {
            _id: '5b7032a84ab34938adbdcb9cf5e24e19',
            _code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            _parameters: '__vue_devtool_undefined__',
            _status: '',
            _detail: '',
        };

        wrapper = await createWrapper(null, ratingError);
        const headlineInput = wrapper.find('.sw-extension-review-creation-inputs__rating .sw-field');

        expect(headlineInput.attributes('class')).toContain('has--error');
    });
});
