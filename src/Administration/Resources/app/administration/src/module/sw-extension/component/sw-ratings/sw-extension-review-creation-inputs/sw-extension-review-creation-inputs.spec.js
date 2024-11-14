import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-review-creation-inputs', () => {
    async function createWrapper(headlineError, ratingError) {
        return mount(
            await wrapTestComponent('sw-extension-review-creation-inputs', {
                sync: true,
            }),
            {
                global: {
                    provide: {
                        validationService: {},
                    },
                    stubs: {
                        'sw-text-field': await wrapTestComponent('sw-text-field', { sync: true }),
                        'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                        'sw-contextual-field': await wrapTestComponent('sw-contextual-field', { sync: true }),
                        'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                        'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                        'sw-field-error': await wrapTestComponent('sw-field-error', { sync: true }),
                        'sw-extension-select-rating': await wrapTestComponent('sw-extension-select-rating', { sync: true }),
                        'sw-extension-rating-stars': await wrapTestComponent('sw-extension-rating-stars', { sync: true }),
                        'sw-icon': true,
                        'sw-textarea-field': {
                            template: '<textarea></textarea>',
                        },
                        'sw-field-copyable': true,
                        'sw-inheritance-switch': true,
                        'sw-ai-copilot-badge': true,
                        'sw-help-text': true,
                    },
                },
                props: {
                    errors: {
                        headlineError: headlineError || null,
                        ratingError: ratingError || null,
                    },
                },
            },
        );
    }

    it('headline input field should be required', async () => {
        const wrapper = await createWrapper();
        const headlineLabel = wrapper.get('.sw-extension-review-creation-inputs__grid label');

        expect(headlineLabel.attributes('class')).toBe('is--required');
    });

    it('rating input field should be required', async () => {
        const wrapper = await createWrapper();
        const headlineLabel = wrapper.get('.sw-field__label label');

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

        const wrapper = await createWrapper(headlineError);
        const headlineInput = wrapper.get('.sw-field');

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

        const wrapper = await createWrapper(null, ratingError);
        const headlineInput = wrapper.get('.sw-extension-review-creation-inputs__rating .sw-field');

        expect(headlineInput.attributes('class')).toContain('has--error');
    });
});
